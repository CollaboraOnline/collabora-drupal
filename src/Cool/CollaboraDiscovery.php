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

namespace Drupal\collabora_online\Cool;

use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service to get a WOPI client url for a given MIME type.
 */
class CollaboraDiscovery {

  /**
   * Constructor.
   *
   * @param \Drupal\collabora_online\Cool\CollaboraDiscoveryFetcher $discoveryFetcher
   *   Discovery fetcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(
    protected readonly CollaboraDiscoveryFetcher $discoveryFetcher,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Gets the URL for the WOPI client.
   *
   * @param string $mimetype
   *   Mime type for which to get the WOPI client url.
   *   This refers to config entries in the discovery.xml file.
   *
   * @return string
   *   The WOPI client url.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  public function getWopiClientURL(string $mimetype = 'text/plain'): string {
    $cool_settings = $this->configFactory->get('collabora_online.settings')->get('cool');
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

    $xml = $this->discoveryFetcher->getDiscoveryXml($wopi_client_server);

    $discovery_parsed = simplexml_load_string($xml);
    if (!$discovery_parsed) {
      throw new CollaboraNotAvailableException(
        'The retrieved discovery.xml file is not a valid XML file.',
        102,
      );
    }

    $result = $discovery_parsed->xpath(sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $mimetype));
    if (empty($result[0]['urlsrc'][0])) {
      throw new CollaboraNotAvailableException(
        'The requested mime type is not handled.',
        103,
      );
    }

    return (string) $result[0]['urlsrc'][0];
  }

}
