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

use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
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
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The key to use by Collabora is empty or not configured.
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
    if (!isset($payload['exp']) || $payload['exp'] < gettimeofday(TRUE)) {
      // The token is expired, or no timeout was set.
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
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The key to use by Collabora is empty or not configured.
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
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The key to use by Collabora is empty or not configured.
   */
  protected function getKey(): string {
    $cool_settings = $this->configFactory->get('collabora_online.settings')->get('cool');
    $key_id = $cool_settings['key_id'] ?? '';
    if (!$key_id) {
      throw new CollaboraNotAvailableException('No key was chosen for use in Collabora.');
    }
    $key = $this->keyRepository->getKey($key_id)?->getKeyValue();
    if (!$key) {
      throw new CollaboraNotAvailableException(sprintf("The key with id '%s' is empty or does not exist.", $key_id));
    }
    return $key;
  }

}
