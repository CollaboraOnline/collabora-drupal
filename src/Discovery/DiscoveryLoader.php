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
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * Creates a discovery value object.
 */
class DiscoveryLoader implements DiscoveryLoaderInterface {

  public function __construct(
    protected readonly CollaboraDiscoveryFetcherInterface $discoveryFetcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDiscovery(): CollaboraDiscoveryInterface {
    $cacheability = new CacheableMetadata();
    $xml = $this->discoveryFetcher->getDiscoveryXml($cacheability);
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

}
