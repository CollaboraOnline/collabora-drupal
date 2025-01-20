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

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;

/**
 * Common base class for JWT transcoders, with the actual transcoding logic.
 *
 * This allows to unit-test the encode and decode functionality, without mocking
 * the services that provide the key.
 */
abstract class JwtTranscoderBase implements JwtTranscoderInterface {

  public function __construct(
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function decode(
    #[\SensitiveParameter]
    string $token,
  ): array|null {
    $key = $this->getKey();
    try {
      $payload = (array) JWT::decode($token, new Key($key, 'HS256'));
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return NULL;
    }
    if (!isset($payload['exp'])) {
      // The token does not have an expiration timestamp.
      return NULL;
    }
    return $payload;
  }

  /**
   * {@inheritdoc}
   */
  public function encode(array $payload, int|float $expire_timestamp): string {
    $payload['exp'] = $expire_timestamp;
    $key = $this->getKey();
    $jwt = JWT::encode($payload, $key, 'HS256');

    return $jwt;
  }

  /**
   * Obtains the signing key from the key storage.
   *
   * @return string
   *   The key value.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraJwtKeyException
   *   The key to use by Collabora is empty or not configured.
   */
  abstract protected function getKey(): string;

}
