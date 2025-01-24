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
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service to load the discovery.xml from the Collabora server.
 */
class CollaboraDiscoveryFetcher implements CollaboraDiscoveryFetcherInterface {

  public function __construct(
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ClientInterface $httpClient,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDiscoveryXml(RefinableCacheableDependencyInterface $cacheability): string {
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
