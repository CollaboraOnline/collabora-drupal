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

namespace Drupal\collabora_online\Plugin\KeyType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\key\Plugin\KeyTypeBase;

/**
 * Defines a key type for JWT HMAC Signatures.
 *
 * @KeyType(
 *   id = "collabora_jwt_hs",
 *   label = @Translation("JWT HMAC - Collabora Online"),
 *   description = @Translation("A key tailored for the use in Collabora."),
 *   group = "encryption",
 *   key_value = {
 *     "plugin" = "text_field"
 *   }
 * )
 */
class CollaboraJwtHs extends KeyTypeBase {

  protected const BYTES = 256 / 8;

  /**
   * {@inheritdoc}
   */
  public static function generateKeyValue(mixed $configuration): string {
    // Generate a key twice as long as the minimum required.
    return random_bytes(2 * self::BYTES);
  }

  /**
   * {@inheritdoc}
   */
  public function validateKeyValue(array $form, FormStateInterface $form_state, $key_value): void {
    // Validate the key size.
    $key_length = $key_value ? strlen($key_value) : 0;
    if ($key_length < self::BYTES) {
      $args = ['%size' => $key_length * 8, '%required' => self::BYTES * 8];
      $form_state->setErrorByName('algorithm', $this->t('Key size (%size bits) is too small for algorithm chosen. Algorithm requires a minimum of %required bits.', $args));
    }
  }

}
