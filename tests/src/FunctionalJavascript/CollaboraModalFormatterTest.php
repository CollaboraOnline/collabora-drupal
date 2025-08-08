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
use Drupal\media\MediaInterface;
use Drupal\Tests\collabora_online\Traits\MediaCreationTrait;
use Drupal\Tests\collabora_online\Traits\MediaFormatterTrait;
use Drupal\Tests\RandomGeneratorTrait;

/**
 * @coversDefaultClass \Drupal\collabora_online\Plugin\Field\FieldFormatter\CollaboraPreviewModal
 */
class CollaboraModalFormatterTest extends WebDriverTestBase {

  use MediaFormatterTrait;
  use RandomGeneratorTrait;
  use MediaCreationTrait;

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

    // Enable standalone media page.
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
      // Use an uncommon number, which is later used in an assertion.
      'max_width' => 753,
    ]);
  }

  /**
   * Tests the display of the modal formatter.
   */
  public function testModalFormatterDisplay(): void {
    $media = $this->createMediaEntity('document', [
      'name' => 'Test document',
    ]);
    // A user with insufficient permissions can see the media, but not the
    // preview button.
    $account = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($account);
    $expected_modal_url = '/cool/modal/' . $media->id();
    $this->drupalGet($media->toUrl());
    $assert_session = $this->assertSession();
    $this->assertSame(
      'Test document | Drupal',
      $assert_session->elementExists('css', 'title')
        ->getHtml(),
    );
    $assert_session->pageTextContains('Test document');
    $assert_session->pageTextNotContains('Field with attached file');
    $assert_session->pageTextNotContains('Preview');
    $assert_session->elementNotExists('css', "a[href='$expected_modal_url']");

    // A user with sufficient permissions can see the preview button.
    $account = $this->drupalCreateUser([
      'access content',
      'preview document in collabora',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet($media->toUrl());
    $assert_session->pageTextContains('Field with attached file');
    $button = $this->assertPreviewButton($media);

    // No dialog or iframe exists (yet).
    $assert_session->elementNotExists('css', '.ui-dialog');
    $assert_session->elementNotExists('css', '.ui-dialog-content');
    $assert_session->elementNotExists('css', 'iframe');

    // Clicking the button opens the modal.
    $button->click();
    $iframe = $this->assertModalWithIframe($media);

    // Clicking the close button closes the modal.
    $assert_session->elementExists('named', ['button', 'Close'], $iframe->getParent()->getParent())->click();
    // All parts of the dialog are gone.
    $assert_session->elementNotExists('css', '.ui-dialog');
    $assert_session->elementNotExists('css', '.ui-dialog-content');
    $assert_session->elementNotExists('css', 'iframe');
  }

  /**
   * Asserts that a preview button exists as expected, and returns it.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being displayed.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The preview button.
   */
  protected function assertPreviewButton(MediaInterface $media): NodeElement {
    $button = $this->assertSession()->elementExists('named', ['link', 'Preview']);

    // Assert button attributes.
    $this->assertSame('/cool/modal/' . $media->id(), $button->getAttribute('href'));

    return $button;
  }

  /**
   * Waits for the modal dialog, and asserts its properties.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being displayed.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The iframe element.
   */
  protected function assertModalWithIframe(MediaInterface $media): NodeElement {
    $assert_session = $this->assertSession();
    $iframe = $assert_session->waitForElementVisible('css', '.ui-dialog.cool-modal-preview > .ui-dialog-titlebar + .ui-dialog-content > iframe.cool-iframe');
    $this->assertSame('/cool/view/' . $media->id(), $iframe->getAttribute('src'));

    $this->assertStringContainsString(
      // The inline style is coming from the plugin settings.
      // Start with space to avoid matching max-width, if that exists.
      ' width: 753px;',
      $iframe->getParent()->getParent()->getAttribute('style'),
    );

    $this->assertNoJsConsoleLogs();

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
