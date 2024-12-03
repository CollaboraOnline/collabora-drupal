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

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encodes and decodes a JWT token.
 */
class JwtTranscoder {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'key.repository')]
    protected readonly KeyRepositoryInterface $keyRepository,
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
  ) {}

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
      return NULL;
    }
    if ($payload['exp'] < gettimeofday(TRUE)) {
      // Token is expired.
      return NULL;
    }
    return $payload;
  }

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
   */
  public function encode(array $payload, int|float $expire_timestamp): string {
    $payload['exp'] = $expire_timestamp;
    $key = $this->getKey();
    $jwt = JWT::encode($payload, $key, 'HS256');

    return $jwt;
  }

  /**
   * Gets a token expiration timestamp based on the configured TTL.
   *
   * @return float
   *   Expiration timestamp in seconds, with millisecond accuracy.
   */
  public function getExpireTimestamp(): float {
    $cool_settings = $this->configFactory->get('collabora_online.settings')->get('cool');
    $ttl_seconds = $cool_settings['access_token_ttl'] ?? 0;
    // Set a fallback of 24 hours.
    $ttl_seconds = $ttl_seconds ?: 86400;

    return gettimeofday(TRUE) + $ttl_seconds;
  }

  /**
   * Obtains the signing key from the key storage.
   *
   * @return string
   *   The key value.
   */
  protected function getKey(): string {
    $cool_settings = $this->configFactory->get('collabora_online.settings')->get('cool');
    $key_id = $cool_settings['key_id'];

    $key = $this->keyRepository->getKey($key_id)->getKeyValue();
    return $key;
  }

}
