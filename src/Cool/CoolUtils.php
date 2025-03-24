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

use Drupal\collabora_online\Discovery\DiscoveryFetcherInterface;

/**
 * Class with various static methods.
 */
class CoolUtils {

  /**
   * Determines if a MIME type is supported for editing.
   *
   * @param string $mimetype
   *   File MIME type.
   *
   * @return bool
   *   TRUE if the MIME type is supported for editing.
   *   FALSE if the MIME type can only be opened as read-only.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The discovery.xml is not available.
   */
  public static function canEditMimeType(string $mimetype) {
    if (!$mimetype) {
      return FALSE;
    }
    /** @var \Drupal\collabora_online\Discovery\DiscoveryFetcherInterface $discovery_fetcher */
    $discovery_fetcher = \Drupal::service(DiscoveryFetcherInterface::class);
    $discovery = $discovery_fetcher->getDiscovery();
    $wopi_client_edit_url = $discovery->getWopiClientURL($mimetype, 'edit');
    return $wopi_client_edit_url !== NULL;
  }

}
