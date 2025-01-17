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

/**
 * Value object to get values from the discovery.xml.
 */
class CollaboraDiscovery implements CollaboraDiscoveryInterface {

  /**
   * Constructor.
   *
   * @param \SimpleXMLElement $parsedXml
   *   Parsed XML content.
   * @param list<string> $cacheTags
   *   Cache tags.
   * @param int $cacheMaxAge
   *   Cache max age in seconds.
   */
  public function __construct(
    protected readonly \SimpleXMLElement $parsedXml,
    protected readonly array $cacheTags,
    protected readonly int $cacheMaxAge,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getWopiClientURL(string $mimetype = 'text/plain'): ?string {
    $result = $this->parsedXml->xpath(sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $mimetype));
    if (empty($result[0]['urlsrc'][0])) {
      return NULL;
    }

    return (string) $result[0]['urlsrc'][0];
  }

  /**
   * {@inheritdoc}
   */
  public function getProofKey(): ?string {
    $attribute = $this->parsedXml->xpath('/wopi-discovery/proof-key/@value')[0] ?? NULL;
    return $attribute?->__toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getProofKeyOld(): ?string {
    $attribute = $this->parsedXml->xpath('/wopi-discovery/proof-key/@oldvalue')[0] ?? NULL;
    return $attribute?->__toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->cacheTags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->cacheMaxAge;
  }

}
