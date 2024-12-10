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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encodes and decodes a JWT token.
 */
class JwtTranscoder extends JwtTranscoderBase {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'key.repository')]
    protected readonly KeyRepositoryInterface $keyRepository,
    #[Autowire(service: 'logger.channel.collabora_online')]
    LoggerInterface $logger,
  ) {
    parent::__construct($logger);
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
