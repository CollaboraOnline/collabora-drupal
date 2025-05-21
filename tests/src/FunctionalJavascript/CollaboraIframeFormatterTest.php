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
    [$iframe_box, $parent_box] = $this->getSession()->evaluateScript(<<<JS
[
  document.querySelector('iframe').getBoundingClientRect(),
  document.querySelector('iframe').parentElement.getBoundingClientRect(),
]
JS);

    // The iframe size and position is exactly as its parent element.
    $this->assertSame($parent_box, $iframe_box);

    // The iframe width is as expected.
    $this->assertSame($expected_width, $iframe_box['width']);

    // The aspect ratio is as configured.
    $this->assertEqualsWithDelta(2.5, $iframe_box['width'] / $iframe_box['height'], .01);
  }

}
