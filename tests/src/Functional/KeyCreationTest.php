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

namespace Drupal\Tests\collabora_online\Functional;

use Drupal\key\Entity\Key;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the CollaboraJwtHs key works correctly with the key module.
 */
class KeyCreationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests creating a Collabora key through the UI.
   */
  public function testCreateKey(): void {
    $admin = $this->drupalCreateUser(['administer keys']);
    $this->drupalLogin($admin);
    $assert_session = $this->assertSession();
    // Go to the Key list page.
    $this->drupalGet('admin/config/system/keys/add');
    $form_values = [
      'Machine-readable name' => 'collabora_test',
      'Key name' => 'Collabora test key',
      'Key type' => 'collabora_jwt_hs',
      'Key provider' => 'config',
      'Key value' => 'xyz',
    ];
    $this->submitForm($form_values, 'Save');
    $assert_session->statusMessageContains('The key is too short (3 bytes). It must be at least 32 bytes long for the HS256 algorithm.');
    $form_values = [
      'Key value' => 'wwHYDJCstKi7pBfwtiV4y5iEDtnGaS+ALk2OR7DO5EZxSYkcnak5b0v1ZvdlpFXKP+RGijZvh7r+geV4SHJ4kw==',
    ];
    $this->submitForm($form_values, 'Save');
    $assert_session->statusMessageContains('The key Collabora test key has been added.');
    $key = Key::load('collabora_test');
    $this->assertSame('wwHYDJCstKi7pBfwtiV4y5iEDtnGaS+ALk2OR7DO5EZxSYkcnak5b0v1ZvdlpFXKP+RGijZvh7r+geV4SHJ4kw==', $key->getKeyValue());
  }

}
