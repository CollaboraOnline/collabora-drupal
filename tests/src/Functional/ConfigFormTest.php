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
use Drupal\key\Entity\Key;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Collabora configuration.
 *
 * @coversDefaultClass \Drupal\collabora_online\Form\ConfigForm
 */
class ConfigFormTest extends BrowserTestBase {

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
  public function testConfigForm(): void {
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
    $assert_session->fieldValueEquals('Discovery cache TTL', '43200');
    $assert_session->fieldValueEquals('WOPI host URL', 'https://localhost/');
    $assert_session->fieldValueEquals('JWT private key', '');
    $assert_session->fieldValueEquals('Access Token Expiration', '86400');
    $assert_session->fieldValueEquals('Create new file on save after…', '60');
    $assert_session->checkboxNotChecked('Disable TLS certificate check for COOL.');
    $assert_session->checkboxChecked('Verify proof header and timestamp in incoming WOPI requests.');
    $assert_session->checkboxChecked('Allow COOL to use fullscreen mode.');

    // The key select element has no options, because no compatible key exists.
    $this->assertSame(
      [
        '- Select a key -' => '- Select a key -',
      ],
      $this->getOptions('JWT private key'),
    );

    $this->createKeys();

    // The key select options contains only compatible keys.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $this->assertSame(
      [
        '- Select a key -' => '- Select a key -',
        'collabora_test' => 'Collabora test key',
      ],
      $this->getOptions('JWT private key'),
    );

    // Change the form values, then submit the form.
    $assert_session->fieldExists('Collabora Online server URL')
      ->setValue('http://collaboraserver.com/');
    $assert_session->fieldExists('Discovery cache TTL')
      ->setValue('12345');
    $assert_session->fieldExists('WOPI host URL')
      ->setValue('http://wopihost.com/');
    $assert_session->fieldExists('JWT private key')
      ->setValue('collabora_test');
    $assert_session->fieldExists('Access Token Expiration')
      ->setValue('3600');
    $assert_session->fieldExists('Create new file on save after…')
      ->setValue('300');
    $assert_session->fieldExists('Disable TLS certificate check for COOL.')
      ->check();
    $assert_session->fieldExists('Verify proof header and timestamp in incoming WOPI requests.')
      ->uncheck();
    // Since default is checked we disable the full screen option.
    $assert_session->fieldExists('Allow COOL to use fullscreen mode.')
      ->uncheck();
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    // The settings have been updated.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->fieldValueEquals('Collabora Online server URL', 'http://collaboraserver.com/');
    $assert_session->fieldValueEquals('Discovery cache TTL', '12345');
    $assert_session->fieldValueEquals('WOPI host URL', 'http://wopihost.com/');
    $assert_session->fieldValueEquals('JWT private key', 'collabora_test');
    $assert_session->fieldValueEquals('Access Token Expiration', '3600');
    $assert_session->fieldValueEquals('Create new file on save after…', '300');
    $assert_session->checkboxChecked('Disable TLS certificate check for COOL.');
    $assert_session->checkboxNotChecked('Verify proof header and timestamp in incoming WOPI requests.');
    $assert_session->checkboxNotChecked('Allow COOL to use fullscreen mode.');

    // Test validation of required fields.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->fieldExists('Collabora Online server URL')->setValue('');
    $assert_session->fieldExists('Discovery cache TTL')->setValue('');
    $assert_session->fieldExists('WOPI host URL')->setValue('');
    $assert_session->fieldExists('JWT private key')->setValue('');
    $assert_session->fieldExists('Access Token Expiration')->setValue('');
    $assert_session->fieldExists('Create new file on save after…')->setValue('');
    $assert_session->fieldExists('Disable TLS certificate check for COOL.')->uncheck();
    $assert_session->fieldExists('Allow COOL to use fullscreen mode.')->uncheck();
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('Collabora Online server URL field is required.', 'error');
    $assert_session->statusMessageContains('Discovery cache TTL field is required.', 'error');
    $assert_session->statusMessageContains('WOPI host URL field is required.', 'error');
    $assert_session->statusMessageContains('JWT private key field is required.', 'error');
    $assert_session->statusMessageContains('Access Token Expiration field is required.', 'error');
    $assert_session->statusMessageContains('Create new file on save after… field is required.', 'error');

    // Test validation of bad form values.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    // Set invalid value for URL fields.
    $assert_session->fieldExists('Collabora Online server URL')->setValue('/internal');
    $assert_session->fieldExists('Discovery cache TTL')->setValue('-1');
    $assert_session->fieldExists('WOPI host URL')->setValue('any-other-value');
    // Set invalid values for numeric field.
    $assert_session->fieldExists('Access Token Expiration')->setValue('text');
    $assert_session->fieldExists('Create new file on save after…')->setValue('text');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('Discovery cache TTL must be higher than or equal to 0.
', 'error');
    $assert_session->statusMessageContains('The URL /internal is not valid.', 'error');
    $assert_session->statusMessageContains('The URL any-other-value is not valid.', 'error');
    $assert_session->statusMessageContains('Access Token Expiration must be a number.', 'error');
    $assert_session->statusMessageContains('Create new file on save after… must be a number.', 'error');

    // Test form with no configuration.
    \Drupal::configFactory()->getEditable('collabora_online.settings')->setData([])->save();
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->fieldValueEquals('Collabora Online server URL', '');
    $assert_session->fieldValueEquals('Discovery cache TTL', '43200');
    $assert_session->fieldValueEquals('WOPI host URL', '');
    $assert_session->fieldValueEquals('JWT private key', '');
    $assert_session->fieldValueEquals('Access Token Expiration', '0');
    $assert_session->fieldValueEquals('Create new file on save after…', '0');
    $assert_session->checkboxNotChecked('Disable TLS certificate check for COOL.');
    $assert_session->checkboxChecked('Verify proof header and timestamp in incoming WOPI requests.');
    $assert_session->checkboxNotChecked('Allow COOL to use fullscreen mode.');
    $assert_session->buttonExists('Save configuration');
  }

  /**
   * Creates some keys.
   */
  protected function createKeys(): void {
    // Create a JWT key, which we can then choose in the form.
    Key::create([
      'id' => 'collabora_test',
      'label' => 'Collabora test key',
      'key_type' => 'collabora_jwt_hs',
      'key_provider' => 'config',
    ])->save();

    // Create another key which should not appear in the select.
    Key::create([
      'id' => 'other_key',
      'label' => 'Other key',
    ])->save();
  }

}
