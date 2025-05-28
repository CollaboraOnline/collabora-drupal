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
class Discovery implements DiscoveryInterface {

  /**
   * Constructor.
   *
   * @param \SimpleXMLElement $parsedXml
   *   Parsed XML content.
   */
  public function __construct(
    protected readonly \SimpleXMLElement $parsedXml,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getWopiClientURL(string $mimetype, string $action): ?string {
    $result = $this->parsedXml->xpath(sprintf(
      "/wopi-discovery/net-zone/app[@name='%s']/action[@name='%s']",
      $mimetype,
      $action,
    ));
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

}
