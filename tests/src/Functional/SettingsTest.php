<?php

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

    // Check fields and default values.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->fieldValueEquals('Collabora Online server URL', 'https://localhost:9980/');
    $assert_session->fieldValueEquals('WOPI host URL', 'https://localhost/');
    $assert_session->fieldValueEquals('JWT private key ID', 'cool');
    $assert_session->fieldValueEquals('Access Token Expiration (in seconds)', '86400');
    $assert_session->fieldValueEquals('Disable TLS certificate check for COOL.', '');
    $assert_session->fieldValueEquals('Allow COOL to use fullscreen mode.', '1');

    // Check that fields values are saved.
    $assert_session->fieldExists('Collabora Online server URL')
      ->setValue('http://collaboraserver.com/');
    $assert_session->fieldExists('WOPI host URL')
      ->setValue('http://wopihost.com/');
    $assert_session->fieldExists('JWT private key ID')
      ->setValue('a_random_token');
    $assert_session->fieldExists('Access Token Expiration (in seconds)')
      ->setValue('3600');
    $assert_session->fieldExists('Disable TLS certificate check for COOL.')
      ->check();
    // Since default is checked we disable the full screen option.
    $assert_session->fieldExists('Allow COOL to use fullscreen mode.')
      ->uncheck();
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    // Check the values in fields.
    $this->drupalGet(Url::fromRoute('collabora-online.settings'));
    $assert_session->fieldValueEquals('Collabora Online server URL', 'http://collaboraserver.com/');
    $assert_session->fieldValueEquals('WOPI host URL', 'http://wopihost.com');
    $assert_session->fieldValueEquals('JWT private key ID', 'a_random_token');
    $assert_session->fieldValueEquals('Access Token Expiration (in seconds)', '3600');
    $assert_session->fieldValueEquals('Disable TLS certificate check for COOL.', '1');
    $assert_session->fieldValueEquals('Allow COOL to use fullscreen mode.', '');
  }

}
