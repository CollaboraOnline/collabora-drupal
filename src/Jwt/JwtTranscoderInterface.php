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

namespace Drupal\collabora_online\Jwt;

/**
 * Encodes and decodes a JWT token.
 */
interface JwtTranscoderInterface {

  /**
   * Decodes and verifies a JWT token.
   *
   * Verification include:
   *  - matching $id with fid in the payload
   *  - verifying the expiration.
   *
   * @param string $token
   *   The token to verify.
   *
   * @return array|null
   *   Data decoded from the token, or NULL on failure or if the token has
   *   expired.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The key to use by Collabora is empty or not configured.
   */
  public function decode(string $token): array|null;

  /**
   * Creates a JWT token.
   *
   * @param array $payload
   *   Values to encode in the token.
   * @param int|float $expire_timestamp
   *   Expiration timestamp, in seconds.
   *
   * @return string
   *   The access token.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The key to use by Collabora is empty or not configured.
   */
  public function encode(array $payload, int|float $expire_timestamp): string;

}
