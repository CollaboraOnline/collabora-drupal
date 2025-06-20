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
 * Service to get a WOPI client URL for a given MIME type.
 */
interface DiscoveryInterface {

  /**
   * Gets the URL for the WOPI client.
   *
   * @param string $mimetype
   *   Mime type for which to get the WOPI client URL.
   *   This refers to config entries in the discovery.xml file.
   *
   * @return string|null
   *   The WOPI client URL, or NULL if none provided for the MIME type.
   */
  public function getWopiClientURL(string $mimetype = 'text/plain'): ?string;

  /**
   * Gets the URL for the settings iframe.
   *
   * @return string|null
   *   The settings iframe URL, or NULL if not supported.
   */
  public function getSettingsIframeURL(): ?string;

  /**
   * Gets the public key used for proofing.
   *
   * @return string|null
   *   The recent key, or NULL if none found.
   */
  public function getProofKey(): ?string;

  /**
   * Gets the old public key for proofing.
   *
   * This covers the case when the public key was already updated, but an
   * incoming request has a proof that was generated with the previous key.
   *
   * @return string|null
   *   The old key, or NULL if none found.
   */
  public function getProofKeyOld(): ?string;

}
