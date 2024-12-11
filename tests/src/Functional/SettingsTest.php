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

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Collabora configuration.
 */
class SettingsTest extends BrowserTestBase {

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
   * Tests the configuration for the Collabora settings form.
   */
  public function testSettingsForm(): void {
    $assert_session = $this->assertSession();

    // User without permission can't access the configuration page.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->pageTextContains('You are not authorized to access this page.');
    $assert_session->statusCodeEquals(403);

    // User with permission can access the configuration page.
    $user = $this->createUser(['administer site configuration']);
    $this->drupalLogin($user);
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->statusCodeEquals(200);

    // The form contains default values from module install.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->fieldValueEquals('Collabora Online server URL', 'https://localhost:9980/');
    $assert_session->fieldValueEquals('WOPI host URL', 'https://localhost/');
    $assert_session->fieldValueEquals('JWT private key ID', 'cool');
    $assert_session->fieldValueEquals('Access Token Expiration (in seconds)', '86400');
    $assert_session->fieldValueEquals('Disable TLS certificate check for COOL.', '');
    $assert_session->fieldValueEquals('Allow COOL to use fullscreen mode.', '1');

    // Change the form values, then submit the form.
    $assert_session->fieldExists('Collabora Online server URL')
      ->setValue('http://collaboraserver.com/');
    $assert_session->fieldExists('WOPI host URL')
      ->setValue('http://wopihost.com/');
    $assert_session->fieldExists('JWT private key ID')
      ->setValue('name_of_a_key');
    $assert_session->fieldExists('Access Token Expiration (in seconds)')
      ->setValue('3600');
    $assert_session->fieldExists('Disable TLS certificate check for COOL.')
      ->check();
    // Since default is checked we disable the full screen option.
    $assert_session->fieldExists('Allow COOL to use fullscreen mode.')
      ->uncheck();
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    // The settings have been updated.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->fieldValueEquals('Collabora Online server URL', 'http://collaboraserver.com/');
    // Slash is removed at the end of Wopi URL.
    $assert_session->fieldValueEquals('WOPI host URL', 'http://wopihost.com');
    $assert_session->fieldValueEquals('JWT private key ID', 'name_of_a_key');
    $assert_session->fieldValueEquals('Access Token Expiration (in seconds)', '3600');
    $assert_session->fieldValueEquals('Disable TLS certificate check for COOL.', '1');
    $assert_session->fieldValueEquals('Allow COOL to use fullscreen mode.', '');

    // Test validation of required fields.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->fieldExists('Collabora Online server URL')->setValue('');
    $assert_session->fieldExists('WOPI host URL')->setValue('');
    $assert_session->fieldExists('JWT private key ID')->setValue('');
    $assert_session->fieldExists('Access Token Expiration (in seconds)')->setValue('');
    $assert_session->fieldExists('Disable TLS certificate check for COOL.')->uncheck();
    $assert_session->fieldExists('Allow COOL to use fullscreen mode.')->uncheck();
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('Collabora Online server URL field is required.', 'error');
    $assert_session->statusMessageContains('WOPI host URL field is required.', 'error');
    $assert_session->statusMessageContains('JWT private key ID field is required.', 'error');
    $assert_session->statusMessageContains('Access Token Expiration (in seconds) field is required.', 'error');

    // Test validation of bad form values.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    // Set invalid value for URL fields.
    $assert_session->fieldExists('Collabora Online server URL')->setValue('/internal');
    $assert_session->fieldExists('WOPI host URL')->setValue('any-other-value');
    // Set invalid values for numeric field.
    $assert_session->fieldExists('Access Token Expiration (in seconds)')->setValue('text');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The URL /internal is not valid.', 'error');
    $assert_session->statusMessageContains('The URL any-other-value is not valid.', 'error');
    $assert_session->statusMessageNotContains('Access Token Expiration (in seconds) must be a number.', 'status');
  }

}
