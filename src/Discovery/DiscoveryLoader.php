<?php

/*
 * Copyright the Collabora Online contributors.
 *
 * SPDX-License-Identifier: MPL-2.0
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

declare(strict_types=1);

namespace Drupal\collabora_online\Discovery;

use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * Creates a discovery value object.
 */
class DiscoveryLoader implements DiscoveryLoaderInterface {

  protected const MEMORY_CID = 'collabora_online.discovery.value';

  protected const DEFAULT_CID = 'collabora_online.discovery';

  public function __construct(
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ClientInterface $httpClient,
    #[Autowire(service: 'cache.default')]
    protected readonly CacheBackendInterface $cache,
    #[Autowire(value: self::DEFAULT_CID)]
    protected readonly string $cid,
    protected readonly MemoryCacheInterface $memoryCache,
    protected readonly TimeInterface $time,
    #[Autowire(value: self::MEMORY_CID)]
    protected readonly string $memoryCid,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDiscovery(): CollaboraDiscoveryInterface {
    $cached = $this->memoryCache->get($this->memoryCid);
    if ($cached) {
      return $cached->data;
    }
    $discovery = $this->loadDiscovery();

    $max_age = $discovery->getCacheMaxAge();
    /* @see \Drupal\Core\Cache\VariationCache::maxAgeToExpire() */
    $expire = ($max_age === Cache::PERMANENT)
      ? Cache::PERMANENT
      : $max_age + $this->time->getRequestTime();
    $this->memoryCache->set(
      $this->memoryCid,
      $discovery,
      $expire,
      $discovery->getCacheTags(),
    );
    return $discovery;
  }

  /**
   * Loads the discovery.
   *
   * @return \Drupal\collabora_online\Discovery\CollaboraDiscoveryInterface
   *   Discovery object.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   Fetching the discovery.xml failed, or the result is not valid xml.
   */
  protected function loadDiscovery(): CollaboraDiscoveryInterface {
    $cacheability = new CacheableMetadata();
    $xml = $this->getDiscoveryXml($cacheability);
    assert($cacheability->getCacheContexts() === []);
    $parsed_xml = $this->parseXml($xml);
    return new CollaboraDiscovery(
      $parsed_xml,
      $cacheability->getCacheTags(),
      $cacheability->getCacheMaxAge(),
    );
  }

  /**
   * Parses an XML string.
   *
   * @param string $xml
   *   XML string.
   *
   * @return \SimpleXMLElement
   *   Parsed XML.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The XML is invalid or empty.
   */
  protected function parseXml(string $xml): \SimpleXMLElement {
    try {
      // Avoid errors from XML parsing hitting the regular error handler.
      // An alternative would be libxml_use_internal_errors(), but then we would
      // have to deal with the results from libxml_get_errors().
      $parsed_xml = ErrorHandler::call(
        fn () => simplexml_load_string($xml),
      );
    }
    catch (\ErrorException $e) {
      throw new CollaboraNotAvailableException('Error in the retrieved discovery.xml file: ' . $e->getMessage(), previous: $e);
    }
    if ($parsed_xml === FALSE) {
      // The parser returned FALSE, but no error was raised.
      // This is known to happen when $xml is an empty string.
      // Instead we could check for $xml === '' earlier, but we don't know for
      // sure if this is, and always will be, the only such case.
      throw new CollaboraNotAvailableException('The discovery.xml file is empty.');
    }
    return $parsed_xml;
  }

  /**
   * Gets the contents of discovery.xml from the Collabora server.
   *
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Mutable object to collect cache metadata.
   *
   * @return string
   *   The full contents of discovery.xml.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  protected function getDiscoveryXml(RefinableCacheableDependencyInterface $cacheability): string {
    $cached = $this->cache->get($this->cid);
    if ($cached) {
      $cacheability->addCacheTags($cached->tags);
      $expire = $cached->expire;
      $max_age = ($expire === Cache::PERMANENT)
        ? Cache::PERMANENT
        : $expire - $this->time->getRequestTime();
      $cacheability->mergeCacheMaxAge($max_age);
      return $cached->data;
    }
    // In theory, the $cacheability could already contain unrelated cache
    // metadata when this method is called. We need to make sure that these do
    // not leak into the cache.
    $local_cacheability = new CacheableMetadata();
    $xml = $this->loadDiscoveryXml($local_cacheability);
    $max_age = $local_cacheability->getCacheMaxAge();

    $cacheability->addCacheableDependency($local_cacheability);

    /* @see \Drupal\Core\Cache\VariationCache::maxAgeToExpire() */
    $expire = ($max_age === Cache::PERMANENT)
      ? Cache::PERMANENT
      : $max_age + $this->time->getRequestTime();
    $this->cache->set(
      $this->cid,
      $xml,
      $expire,
      $local_cacheability->getCacheTags(),
    );
    return $xml;
  }

  /**
   * Loads the contents of discovery.xml from the Collabora server.
   *
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Mutable object to collect cache metadata.
   *
   * @return string
   *   The full contents of discovery.xml.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  public function loadDiscoveryXml(RefinableCacheableDependencyInterface $cacheability): string {
    $config = $this->configFactory->get('collabora_online.settings');
    $cacheability->addCacheableDependency($config);

    $discovery_url = $this->getDiscoveryUrl($config);
    $disable_checks = (bool) $config->get('cool.disable_cert_check');

    try {
      $cacheability->mergeCacheMaxAge(60 * 60 * 12);
      $response = $this->httpClient->get($discovery_url, [
        RequestOptions::VERIFY => !$disable_checks,
      ]);
      $xml = $response->getBody()->getContents();
    }
    catch (ClientExceptionInterface $e) {
      // The backtrace of a client exception is typically not very
      // interesting. Just log the message.
      $this->logger->error("Failed to fetch from '@url': @message.", [
        '@url' => $discovery_url,
        '@message' => $e->getMessage(),
      ]);
      throw new CollaboraNotAvailableException(
        'Not able to retrieve the discovery.xml file from the Collabora Online server.',
        previous: $e,
      );
    }
    return $xml;
  }

  /**
   * Gets the URL to fetch the discovery.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   Configuration for this module.
   *
   * @return string
   *   URL to fetch the discovery XML.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The WOPI server url is misconfigured.
   */
  protected function getDiscoveryUrl(ImmutableConfig $config): string {
    $wopi_client_server = $config->get('cool.server');
    if (!$wopi_client_server) {
      throw new CollaboraNotAvailableException('The configured Collabora Online server address is empty.');
    }
    $wopi_client_server = trim($wopi_client_server);
    // The trailing slash in the configured URL is optional.
    $wopi_client_server = rtrim($wopi_client_server, '/');

    if (!str_starts_with($wopi_client_server, 'http://') && !str_starts_with($wopi_client_server, 'https://')) {
      throw new CollaboraNotAvailableException(sprintf(
        "The configured Collabora Online server address must begin with 'http://' or 'https://'. Found '%s'.",
        $wopi_client_server,
      ));
    }

    return $wopi_client_server . '/hosting/discovery';
  }

}
