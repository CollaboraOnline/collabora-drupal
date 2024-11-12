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

namespace Drupal\collabora_online\Cool;

use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service to load the discovery.xml from the Collabora server.
 */
class CollaboraDiscoveryFetcher {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client.
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerChannelInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ClientInterface $httpClient,
  ) {}

  /**
   * Gets the contents of discovery.xml from the Collabora server.
   *
   * @return string
   *   The full contents of discovery.xml.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  public function getDiscoveryXml(): string {
    $discovery_url = $this->getWopiClientServerBaseUrl() . '/hosting/discovery';

    $cool_settings = $this->loadSettings();
    $disable_checks = (bool) $cool_settings['disable_cert_check'];

    try {
      $response = $this->httpClient->get($discovery_url, [
        RequestOptions::VERIFY => !$disable_checks,
      ]);
      $xml = $response->getBody()->getContents();
    }
    catch (ClientExceptionInterface $e) {
      $this->logger->error('Cannot fetch from @url.', ['@url' => $discovery_url]);
      throw new CollaboraNotAvailableException(
        'Not able to retrieve the discovery.xml file from the Collabora Online server.',
        203,
        $e,
      );
    }
    return $xml;
  }

  /**
   * Loads the WOPI server url from configuration.
   *
   * @return string
   *   Base URL to access the WOPI server from Drupal.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The WOPI server url is misconfigured, or the protocol does not match
   *   that of the current Drupal request.
   */
  protected function getWopiClientServerBaseUrl(): string {
    $cool_settings = $this->loadSettings();
    $wopi_client_server = $cool_settings['server'];
    if (!$wopi_client_server) {
      throw new CollaboraNotAvailableException(
        'Collabora Online server address is not valid.',
        201,
      );
    }
    $wopi_client_server = trim($wopi_client_server);

    if (!str_starts_with($wopi_client_server, 'http')) {
      throw new CollaboraNotAvailableException(
        'Warning! You have to specify the scheme protocol too (http|https) for the server address.',
        204,
      );
    }

    $host_scheme = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    if (!str_starts_with($wopi_client_server, $host_scheme . '://')) {
      throw new CollaboraNotAvailableException(
        'Collabora Online server address scheme does not match the current page url scheme.',
        202,
      );
    }

    return $wopi_client_server;
  }

  /**
   * Loads the relevant configuration.
   *
   * @return array
   *   Configuration.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The module is not configured.
   */
  protected function loadSettings(): array {
    $cool_settings = $this->configFactory->get('collabora_online.settings')->get('cool');
    if (!$cool_settings) {
      throw new CollaboraNotAvailableException('The Collabora Online connection is not configured.');
    }
    return $cool_settings;
  }

}
