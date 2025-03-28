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

use Drupal\collabora_online\Exception\CollaboraJwtKeyException;
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
   * {@inheritdoc}
   */
  protected function getKey(): string {
    /** @var string $key_id */
    $key_id = $this->configFactory->get('collabora_online.settings')
      ->get('cool.key_id') ?? '';
    if (!$key_id) {
      throw new CollaboraJwtKeyException('No key was chosen for use in Collabora.');
    }
    $key = $this->keyRepository->getKey($key_id)?->getKeyValue();
    if (!$key) {
      throw new CollaboraJwtKeyException(sprintf("The key with id '%s' is empty or does not exist.", $key_id));
    }
    return $key;
  }

}
