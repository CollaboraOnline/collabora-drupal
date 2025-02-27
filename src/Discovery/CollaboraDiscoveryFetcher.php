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
use Drupal\Core\Cache\CacheBackendInterface;
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
class CollaboraDiscoveryFetcher implements CollaboraDiscoveryFetcherInterface {

  public const CID = 'collabora_online.discovery';

  public function __construct(
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ClientInterface $httpClient,
    #[Autowire(service: 'cache.default')]
    protected readonly CacheBackendInterface $cache,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDiscovery(): CollaboraDiscoveryInterface {
    $parsed_xml = $this->getDiscoveryParsedXml();
    return new CollaboraDiscovery($parsed_xml);
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
      /** @var \SimpleXMLElement|false $parsed_xml */
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
      throw new CollaboraNotAvailableException('The discovery.xml file seems to be empty.');
    }
    return $parsed_xml;
  }

  /**
   * Gets the contents of discovery.xml from the Collabora server.
   *
   * @return \SimpleXMLElement
   *   The parsed contents of discovery.xml.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  protected function getDiscoveryParsedXml(): \SimpleXMLElement {
    $cached = $this->cache->get(static::CID);
    if ($cached) {
      assert(is_string($cached->data));
      return $this->parseXml($cached->data);
    }
    $config = $this->configFactory->get('collabora_online.settings');

    $xml = $this->loadDiscoveryXml($config);

    // Parse the XML.
    // If this causes an exception, the code below will not be executed, and
    // nothing written to the cache.
    $parsed_xml = $this->parseXml($xml);

    /** @var non-negative-int $max_age */
    $max_age = $config->get('cool.discovery_cache_ttl') ?? 3600;
    if ($max_age === 0) {
      // The discovery cache is disabled.
      return $parsed_xml;
    }

    $expire = $max_age + $this->time->getRequestTime();

    $this->cache->set(
      static::CID,
      $xml,
      $expire,
      $config->getCacheTags(),
    );
    return $parsed_xml;
  }

  /**
   * Loads the contents of discovery.xml from the Collabora server.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *    Configuration for this module.
   *
   * @return string
   *   The full contents of discovery.xml.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  protected function loadDiscoveryXml(ImmutableConfig $config): string {
    $config = $this->configFactory->get('collabora_online.settings');
    $disable_checks = (bool) $config->get('cool.disable_cert_check');
    $discovery_url = $this->getDiscoveryUrl($config);
    try {
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
    /** @var string $wopi_client_server */
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
