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

/**
 * Service to get a WOPI client URL for a given MIME type.
 */
class CollaboraDiscovery implements CollaboraDiscoveryInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\collabora_online\Cool\CollaboraDiscoveryFetcherInterface $discoveryFetcher
   *   Service to load the discovery.xml from the Collabora server.
   */
  public function __construct(
    protected readonly CollaboraDiscoveryFetcherInterface $discoveryFetcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getWopiClientURL(string $mimetype = 'text/plain'): string {
    $discovery_parsed = $this->getParsedXml();

    $result = $discovery_parsed->xpath(sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $mimetype));
    if (empty($result[0]['urlsrc'][0])) {
      throw new CollaboraNotAvailableException('The requested mime type is not handled.');
    }

    return (string) $result[0]['urlsrc'][0];
  }

  /**
   * Fetches the discovery.xml, and gets the parsed contents.
   *
   * @return \SimpleXMLElement
   *   Parsed xml from the discovery.xml.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   Fetching the discovery.xml failed, or the result is not valid xml.
   */
  protected function getParsedXml(): \SimpleXMLElement {
    $xml = $this->discoveryFetcher->getDiscoveryXml();
    return $this->parseXml($xml);
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
    $parsed_xml = simplexml_load_string($xml);
    if (!$parsed_xml) {
      throw new CollaboraNotAvailableException('The retrieved discovery.xml file is not a valid XML file.');
    }
    return $parsed_xml;
  }

}
