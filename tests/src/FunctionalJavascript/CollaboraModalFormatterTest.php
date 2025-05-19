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

namespace Drupal\Tests\collabora_online\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\collabora_online\Traits\MediaFormatterTrait;
use Drupal\Tests\collabora_online\Traits\TestDocumentTrait;

/**
 * @coversDefaultClass \Drupal\collabora_online\Plugin\Field\FieldFormatter\CollaboraPreviewModal
 */
class CollaboraModalFormatterTest extends WebDriverTestBase {

  use MediaFormatterTrait;
  use TestDocumentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online',
    'collabora_online_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::configFactory()
      ->getEditable('media.settings')
      ->set('standalone_url', TRUE)
      ->save(TRUE);
    $this->container->get('router.builder')->rebuild();

    FieldConfig::load('media.document.field_media_file')
      // Set a more distinctive field name.
      ->set('label', 'Field with attached file')
      ->save();

    $this->setFormatter('collabora_preview_modal', [
      'max_width' => 750,
    ]);

    $this->createTestDocument();
  }

  /**
   * Tests the display of the modal formatter.
   */
  public function testModalFormatterDisplay(): void {
    // A user with insufficient permissions can see the media, but not the
    // preview button.
    $account = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($account);
    $expected_modal_url = '/cool/modal/' . $this->media->id();
    $this->visitMediaPage();
    $assert_session = $this->assertSession();
    $assert_session->pageTextNotContains('Field with attached file');
    $assert_session->pageTextNotContains('Preview');
    $assert_session->elementNotExists('css', "a[href='$expected_modal_url']");

    // A user with sufficient permissions can see the preview button.
    $account = $this->drupalCreateUser([
      'access content',
      'preview document in collabora',
    ]);
    $this->drupalLogin($account);
    $this->visitMediaPage();
    $assert_session->pageTextContains('Field with attached file');
    $button = $this->assertPreviewButton();

    // No dialog or iframe exists (yet).
    $assert_session->elementNotExists('css', '.ui-dialog');
    $assert_session->elementNotExists('css', '.ui-dialog-content');
    $assert_session->elementNotExists('css', 'iframe');

    // Clicking the button opens the modal.
    $button->click();
    $iframe = $this->assertModalWithIframe(750);

    // Clicking the close button closes the modal.
    $assert_session->elementExists('named', ['button', 'Close'], $iframe->getParent()->getParent())->click();
    // All parts of the dialog are gone.
    $assert_session->elementNotExists('css', '.ui-dialog');
    $assert_session->elementNotExists('css', '.ui-dialog-content');
    $assert_session->elementNotExists('css', 'iframe');

    // Test with different themes.
    $this->setActiveTheme('claro');
    $this->visitMediaPage();
    $this->assertPreviewButton()->click();
    $this->assertModalWithIframe(750);

    $this->setActiveTheme('olivero');
    $this->visitMediaPage();
    $this->assertPreviewButton()->click();
    $this->assertModalWithIframe(750);

    // Test narrow viewport in different themes.
    $this->getSession()->resizeWindow(400, 800);
    $this->visitMediaPage();
    $this->assertPreviewButton()->click();
    $this->assertModalWithIframe();

    $this->setActiveTheme('claro');
    $this->visitMediaPage();
    $this->assertPreviewButton()->click();
    $this->assertModalWithIframe();

    $this->setActiveTheme('stark');
    $this->visitMediaPage();
    $this->assertPreviewButton()->click();
    $this->assertModalWithIframe();
  }

  /**
   * Visits the media page, and verifies that the media is shown.
   */
  protected function visitMediaPage(): void {
    $this->drupalGet($this->media->toUrl());
    $assert_session = $this->assertSession();
    $this->assertSame(
      'Test document | Drupal',
      $assert_session->elementExists('css', 'title')
        ->getHtml(),
    );
    $assert_session->pageTextContains('Test document');
  }

  /**
   * Asserts that a preview button exists as expected, and returns it.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The preview button.
   */
  protected function assertPreviewButton(): NodeElement {
    $assert_session = $this->assertSession();
    $button = $assert_session->elementExists('named', ['link', 'Preview']);

    // Assert button attributes.
    $this->assertSame('/cool/modal/' . $this->media->id(), $button->getAttribute('href'));
    $this->assertTrue($button->hasClass('use-ajax'));
    $this->assertTrue($button->hasClass('button'));
    $this->assertTrue($button->hasClass('button--small'));
    $this->assertSame('modal', $button->getAttribute('data-dialog-type'));
    $this->assertSame('{"width":750,"classes":{"ui-dialog":"cool-modal-preview"}}', $button->getAttribute('data-dialog-options'));

    // Wait until the click handler is attached.
    $this->getSession()->getPage()->waitFor(10, function () use ($button) {
      return $button->getAttribute('data-once') === 'ajax';
    });

    return $button;
  }

  /**
   * Waits for the modal dialog, and asserts its properties.
   *
   * @param int|null $expected_dialog_width
   *   Expected dialog width in px, or NULL for auto-sized width.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The iframe element.
   */
  protected function assertModalWithIframe(int|null $expected_dialog_width = NULL): NodeElement {
    $assert_session = $this->assertSession();
    $iframe = $assert_session->waitForElementVisible('css', '.ui-dialog.cool-modal-preview > .ui-dialog-titlebar + .ui-dialog-content > iframe.cool-iframe');
    $this->assertSame('/cool/view/' . $this->media->id(), $iframe->getAttribute('src'));

    $js_eval = $this->getSession()->evaluateScript(...);
    $selectors = [
      'dialog' => 'div.ui-dialog',
      'titlebar' => 'div.ui-dialog-titlebar',
      'content' => 'div.ui-dialog-content',
      'iframe' => 'iframe',
    ];
    $boxes = array_map($this->getBoundingRectangle(...), $selectors);

    $viewport_width = $js_eval('window.innerWidth');
    $viewport_height = $js_eval('window.innerHeight');

    $this->assertNoJsConsoleLogs();
    if ($expected_dialog_width !== NULL) {
      if (!isset($boxes['dialog']['width'])) {
        $this->assertSame('?', [$boxes, $boxes['dialog'], $boxes['dialog']['width']]);
      }
      // The dialog has the expected width.
      // Allow for padding and 'box-sizing: content-box'.
      $this->assertNumberInRange($expected_dialog_width, 12, $boxes['dialog']['width']);
    }
    else {
      // The dialog width is relative to the viewport.
      // Allow for padding and 'box-sizing: content-box'.
      $this->assertNumberInRange($viewport_width * .92 - 1, 12, $boxes['dialog']['width']);
    }
    // The dialog height is a percentage of the viewport height.
    // Allow for padding and 'box-sizing: content-box'.
    $this->assertLessThanOrEqual($viewport_height - 20, $boxes['dialog']['height']);
    $this->assertGreaterThanOrEqual($viewport_height * .8, $boxes['dialog']['height']);

    // The dialog is centered in the viewport.
    $this->assertEqualsWithDelta($boxes['dialog']['left'], $viewport_width - $boxes['dialog']['right'], 2);
    $this->assertEqualsWithDelta($boxes['dialog']['top'], $viewport_height - $boxes['dialog']['bottom'], 2);

    // Assert horizontal positioning of child elements.
    $this->assertNumberInRange($boxes['dialog']['left'], 6, $boxes['content']['left']);
    $this->assertNumberInRange($boxes['dialog']['right'], -6, $boxes['content']['right']);
    $this->assertSame($boxes['content']['left'], $boxes['titlebar']['left']);
    $this->assertSame($boxes['content']['right'], $boxes['titlebar']['right']);

    // Assert vertical positioning of child elements.
    $this->assertNumberInRange($boxes['dialog']['top'], 6, $boxes['titlebar']['top']);
    $this->assertNumberInRange($boxes['titlebar']['bottom'], 4, $boxes['content']['top']);
    $this->assertNumberInRange($boxes['dialog']['bottom'], -6, $boxes['content']['bottom']);

    // The iframe has the same dimensions and position as its parent.
    $this->assertSame($boxes['content'], $boxes['iframe']);

    return $iframe;
  }

  /**
   * Gets current position and dimensions of an element.
   *
   * @param string $selector
   *   A CSS selector.
   *
   * @return array{top: number, height: number, bottom: number, left: number, width: number, right: number}
   *   Position and dimensions of the element.
   */
  protected function getBoundingRectangle(string $selector): array {
    $selector_json = json_encode($selector);
    $script = "document.querySelector($selector_json).getBoundingClientRect()";
    return $this->getSession()->evaluateScript($script);
  }

  /**
   * Changes the active/default theme.
   *
   * @param string $theme
   *   Name of the new theme to set as the default.
   */
  protected function setActiveTheme(string $theme): void {
    $this->container->get('theme_installer')->install([$theme]);
    $this->config('system.theme')->set('default', $theme)->save();
  }

  /**
   * Asserts that a number is in a given range.
   *
   * This is different from ->assertEqualsWithDelta(), in that the delta only
   * works in one direction.
   *
   * @param int|float $expected
   *   First delimiter of the expected range.
   * @param int|float $delta
   *   Negative or positive Value to add to $expected to get the second
   *   delimiter of the expected range.
   * @param int|float $actual
   *   Actual value.
   *
   * @see self::assertEqualsWithDelta()
   */
  protected function assertNumberInRange(int|float $expected, int|float $delta, int|float $actual): void {
    $this->assertGreaterThanOrEqual(min($expected, $expected + $delta), $actual);
    $this->assertLessThanOrEqual(max($expected, $expected + $delta), $actual);
  }

  /**
   * Asserts that there are no JS console log entries in the browser.
   */
  protected function assertNoJsConsoleLogs(): void {
    // Access underlying WebDriver session.
    $driver = $this->getSession()->getDriver();
    assert(method_exists($driver, 'getWebDriverSession'));
    $webDriverSession = $driver->getWebDriverSession();
    $logs = $webDriverSession->log('browser');
    $filtered_logs = array_filter($logs, function (array $record): bool {
      if (preg_match('#/cool/view/\d+ - Failed to load resource#', $record['message'])) {
        return FALSE;
      }
      return TRUE;
    });
    $this->assertSame([], $filtered_logs);
  }

}
