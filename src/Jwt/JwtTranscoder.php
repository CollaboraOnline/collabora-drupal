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
use Drupal\Core\Session\AccountInterface;
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
    protected readonly AccountInterface $currentUser,
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
   * @param int|string $id
   *   Media id for which the token was created.
   *   This could be in string form like '123'.
   *
   * @return \stdClass|null
   *   Data decoded from the token, or NULL on failure or if the token has
   *   expired.
   */
  public function verifyTokenForId(
    #[\SensitiveParameter]
    string $token,
    int|string $id,
  ): \stdClass|null {
    $key = $this->getKey();
    try {
      $payload = JWT::decode($token, new Key($key, 'HS256'));

      if ($payload && ($payload->fid == $id) && ($payload->exp >= gettimeofday(TRUE))) {
        return $payload;
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
    return NULL;
  }

  /**
   * Creates a JWT token for a media entity.
   *
   * The token will carry the following:
   *
   * - fid: the Media id in Drupal.
   * - uid: the User id for the token. Permissions should be checked
   *   whenever.
   * - exp: the expiration time of the token.
   * - wri: if true, then this token has write permissions.
   *
   * The signing key is stored in Drupal key management.
   *
   * @param int|string $id
   *   Media id, which could be in string form like '123'.
   * @param int|float $expire_timestamp
   *   Expiration timestamp, in seconds.
   * @param bool $can_write
   *   TRUE if the token is for an editor in write/edit mode.
   *
   * @return string
   *   The access token.
   */
  public function tokenForFileId(int|string $id, int|float $expire_timestamp, bool $can_write = FALSE): string {
    $payload = [
      'fid' => $id,
      'uid' => $this->currentUser->id(),
      'exp' => $expire_timestamp,
      'wri' => $can_write,
    ];
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
    $default_config = $this->configFactory->get('collabora_online.settings');
    $ttl_seconds = $default_config->get('cool')['access_token_ttl'] ?? 0;
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
    $default_config = $this->configFactory->get('collabora_online.settings');
    $key_id = $default_config->get('cool')['key_id'];

    $key = $this->keyRepository->getKey($key_id)->getKeyValue();
    return $key;
  }

}
