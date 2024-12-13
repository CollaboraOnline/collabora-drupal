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
 * Defines a key type for use in Collabora Online.
 *
 * Having the key plugin here avoids a dependency to the 'jwt' module.
 *
 * @KeyType(
 *   id = "collabora_jwt_hs",
 *   label = @Translation("JWT HMAC - Collabora Online"),
 *   description = @Translation("A key tailored for the use with Collabora Online."),
 *   group = "encryption",
 *   key_value = {
 *     "plugin" = "text_field"
 *   }
 * )
 */
class CollaboraJwtHs extends KeyTypeBase {

  /**
   * Minimum key length in bytes for the HS256 algorithm.
   */
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
    $key_length = strlen($key_value ?? '');
    if ($key_length < self::BYTES) {
      $form_state->setErrorByName(
        'key_value',
        $this->t(
          'The key is too short (%length bytes). It must be at least %required bytes long for the %algorithm algorithm.',
          [
            '%length' => $key_length,
            // Avoid explicit numbers in the translatable string, even if these
            // values are currently hard-coded in the plugin.
            '%required' => self::BYTES,
            '%algorithm' => 'HS256',
          ],
        ),
      );
    }
  }

}
