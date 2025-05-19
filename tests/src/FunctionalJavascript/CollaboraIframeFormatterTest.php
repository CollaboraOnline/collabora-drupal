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

use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\collabora_online\Traits\MediaFormatterTrait;
use Drupal\Tests\collabora_online\Traits\TestDocumentTrait;

/**
 * @coversDefaultClass \Drupal\collabora_online\Plugin\Field\FieldFormatter\CollaboraPreviewIframe
 */
class CollaboraIframeFormatterTest extends WebDriverTestBase {

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

    $this->enableStandaloneMediaPage();

    // Set a more distinctive field name.
    FieldConfig::load('media.document.field_media_file')
      ->set('label', 'Field with attached file')
      ->save();

    $this->setFormatter('collabora_preview_iframe', [
      'aspect_ratio' => '5 / 2',
    ]);

    $this->createTestDocument();
  }

  /**
   * Tests the display of the iframe formatter.
   */
  public function testIframeFormatterDisplay(): void {
    // A user with insufficient permissions can see the media, but not the
    // preview button.
    $account = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($account);
    $this->visitMediaPage();
    $assert_session = $this->assertSession();
    $assert_session->pageTextNotContains('Field with attached file');
    $assert_session->elementNotExists('css', 'iframe');

    // A user with sufficient permissions can see the preview button.
    $account = $this->drupalCreateUser([
      'access content',
      'preview document in collabora',
    ]);
    $this->drupalLogin($account);
    $this->visitMediaPage();
    $assert_session->pageTextContains('Field with attached file');
    $iframe = $assert_session->elementExists('css', 'iframe.cool-iframe');

    // Assert iframe attributes and dimensions.
    $this->assertSame('/cool/view/' . $this->media->id(), $iframe->getAttribute('src'));
    $this->assertSame('aspect-ratio: 5 / 2', $iframe->getAttribute('style'));
    $this->assertIframeDimensions(1000);
  }

  /**
   * Asserts iframe dimensions and position.
   *
   * This verifies that the CSS is correctly applied.
   *
   * @param int $expected_width
   *   Expected width of the iframe.
   */
  protected function assertIframeDimensions(int $expected_width): void {
    // Check rendered width, height and position with javascript.
    $js_eval = $this->getSession()->evaluateScript(...);
    $select_iframe = "document.querySelector('iframe')";

    $iframe_width = $js_eval("$select_iframe.clientWidth");
    $this->assertSame($expected_width, $iframe_width);
    $this->assertSame($iframe_width, $js_eval("$select_iframe.parentElement.clientWidth"));

    // The iframe height should be the same as its parent element.
    $iframe_height = $js_eval("$select_iframe.clientHeight");
    $this->assertSame($iframe_height, $js_eval("$select_iframe.clientHeight"));
    // The ratio of width and height should match the aspect ratio 5 / 2.
    $this->assertEqualsWithDelta(2.5, $iframe_width / $iframe_height, .01);
  }

}
