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

/**
 * Service to get a WOPI client url for a given MIME type.
 */
class CollaboraDiscovery {

  /**
   * Constructor.
   *
   * @param \Drupal\collabora_online\Cool\CollaboraDiscoveryFetcher $discoveryFetcher
   *   Service to load the discovery.xml from the Collabora server.
   */
  public function __construct(
    protected readonly CollaboraDiscoveryFetcher $discoveryFetcher,
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
    $xml = $this->discoveryFetcher->getDiscoveryXml();

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
