<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\ExistingSiteJavascript;

use Drupal\collabora_online\Storage\WopiSettingsStorageInterface;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests the Collabora settings iframe.
 */
class SettingsIframeTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\collabora_online\Storage\WopiSettingsStorageInterface $settings_storage */
    $settings_storage = \Drupal::service(WopiSettingsStorageInterface::class);

    // Fail early if settings storage (private filesystem) is not available.
    $this->assertTrue($settings_storage->isAvailable());

    // Delete all, for a clean starting point.
    $list = $settings_storage->list('systemconfig');
    foreach ($list as $wopi_file_id => $stamp) {
      $this->assertTrue($settings_storage->delete($wopi_file_id), $wopi_file_id);
    }
    $this->assertSame([], $settings_storage->list('systemconfig'));
  }

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

    /** @var \Drupal\collabora_online\Storage\WopiSettingsStorageInterface $settings_storage */
    $settings_storage = \Drupal::service(WopiSettingsStorageInterface::class);
    $this->assertSame([], $settings_storage->list('systemconfig'));
    $wait_stored_settings_update = $this->createWaitForChangeFunction(fn () => $settings_storage->list('systemconfig'));

    $assert_session = $this->assertSession();

    $this->loadSettingsPageAndFocusIframe();

    // Visiting the settings iframe causes empty settings to be populated.
    $list = $wait_stored_settings_update();
    $this->assertSame(['/settings/systemconfig/xcu/documentView.xcu'], array_keys($list));

    // If the stored settings were empty before, the iframe will do multiple
    // redraws of the checkboxes.
    // Wait one second until everything is settled, to avoid stale element
    // references.
    sleep(1);

    // The checkbox is covered in svg, and cannot be operated normally.
    // Also, the label is not in a proper <label> tag.
    $select_checkbox = fn () => $assert_session->elementExists('xpath', "//input[@type='checkbox' and parent::*[contains(., 'SnapToGrid')]]");
    $assert_checked = fn (bool $expected) => $this->assertSame($expected, $select_checkbox()->isChecked());
    $toggle_checkbox = fn () => $select_checkbox()->getParent()->click();

    $assert_checked(FALSE);

    $toggle_checkbox();
    $assert_checked(TRUE);
    $assert_session->buttonExists('Save')->press();
    $wait_stored_settings_update();
    $this->assertTrue($this->readStoredSetting('SnapToGrid'));
    $assert_checked(TRUE);
    $this->loadSettingsPageAndFocusIframe();
    $assert_checked(TRUE);

    $toggle_checkbox();
    $assert_checked(FALSE);
    $assert_session->buttonExists('Save')->press();
    $wait_stored_settings_update();
    $this->assertFalse($this->readStoredSetting('SnapToGrid'));
    $assert_checked(FALSE);
    $this->loadSettingsPageAndFocusIframe();
    $assert_checked(FALSE);
  }

  /**
   * Loads the settings page and switches to the settings iframe.
   */
  protected function loadSettingsPageAndFocusIframe(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('/admin/config/cool/settings');
    $this->getSession()->switchToIFrame('settings-iframe-outer');
    $this->assertNotNull($assert_session->waitForElement('css', 'iframe#collabora-online-settings'));
    $this->getSession()->switchToIFrame('collabora-online-settings');
    $assert_session->buttonExists('Upload Autotext');
    $this->assertTrue($this->assertSession()->waitForText('Document Settings'));
  }

  /**
   * Reads a setting value from the storage.
   *
   * To keep it simple, this method only works for some settings values.
   *
   * @param string $name
   *   The name of a checkbox field.
   *
   * @return bool
   *   The stored checkbox value.
   */
  protected function readStoredSetting(string $name): bool {
    /** @var \Drupal\collabora_online\Storage\WopiSettingsStorageInterface $settings_storage */
    $settings_storage = \Drupal::service(WopiSettingsStorageInterface::class);
    // The .xcu file contains stored checkbox values.
    $xml = $settings_storage->read('/settings/systemconfig/xcu/documentView.xcu');
    $this->assertNotNull($xml);
    $this->assertStringContainsString($name, $xml);
    $tree = simplexml_load_string($xml);
    $strval = $tree->xpath(sprintf(
      '/oor:items/item[@oor:path="/org.openoffice.Office.Calc/Grid/Option"]/prop[@oor:name="%s"]/value',
      $name,
    ))[0]->__toString();
    return match ($strval) {
      'true' => TRUE,
      'false' => FALSE,
    };
  }

  /**
   * Creates a function that waits for a value to change.
   *
   * @param callable(): mixed $load
   *   Callback to load the value.
   *
   * @return \Closure(): mixed
   *   Wait function. The return value is the most recent value from $load().
   */
  protected function createWaitForChangeFunction(callable $load): \Closure {
    $value = $load();
    $condition = function () use (&$value, $load): bool {
      $new_value = $load();
      $pass = $new_value !== $value;
      $value = $new_value;
      return $pass;
    };
    return function () use (&$value, $condition) {
      $success = $this->getSession()->getPage()->waitFor(10, $condition);
      $this->assertTrue($success);
      return $value;
    };
  }

}
