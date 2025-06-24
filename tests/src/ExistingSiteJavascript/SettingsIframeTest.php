<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests the Collabora settings iframe.
 */
class SettingsIframeTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Tests the Collabora settings iframe embedded in the form.
   */
  public function testCollaboraSettingsIframe(): void {
    $user = $this->createUser([
      // This permission is required to see the settings iframe.
      'administer collabora instance',
      // This permission is required to see the form.
      'administer site configuration',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/cool/settings');

    $this->getSession()->switchToIFrame('settings-iframe-outer');
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'iframe#collabora-online-settings'));
    $this->getSession()->switchToIFrame('collabora-online-settings');
    $this->assertSession()->buttonExists('Upload Autotext');
  }

}
