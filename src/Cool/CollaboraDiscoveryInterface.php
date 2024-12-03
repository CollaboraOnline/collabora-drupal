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

/**
 * Service to get a WOPI client URL for a given MIME type.
 */
interface CollaboraDiscoveryInterface {

  /**
   * Gets the URL for the WOPI client.
   *
   * @param string $mimetype
   *   Mime type for which to get the WOPI client URL.
   *   This refers to config entries in the discovery.xml file.
   *
   * @return string
   *   The WOPI client URL.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client URL cannot be retrieved.
   */
  public function getWopiClientURL(string $mimetype = 'text/plain'): string;

}
