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
use Drupal\Tests\collabora_online\Traits\MediaCreationTrait;
use Drupal\Tests\collabora_online\Traits\MediaFormatterTrait;
use Drupal\Tests\RandomGeneratorTrait;

/**
 * @coversDefaultClass \Drupal\collabora_online\Plugin\Field\FieldFormatter\CollaboraPreviewEmbed
 */
class CollaboraIframeFormatterTest extends WebDriverTestBase {

  use RandomGeneratorTrait;
  use MediaCreationTrait;
  use MediaFormatterTrait;

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

    // Set a more distinctive field name.
    FieldConfig::load('media.document.field_media_file')
      ->set('label', 'Field with attached file')
      ->save();

    $this->setFormatter('collabora_preview_embed', [
      'aspect_ratio' => '5 / 2',
    ]);
  }

  /**
   * Tests the display of the iframe formatter.
   */
  public function testIframeFormatterDisplay(): void {
    $media = $this->createMediaEntity('document', [
      'name' => 'Test document',
    ]);
    // A user with insufficient permissions can see the media, but not the
    // preview button.
    $account = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet($media->toUrl());
    $assert_session = $this->assertSession();
    $this->assertSame(
      'Test document | Drupal',
      $assert_session->elementExists('css', 'title')
        ->getHtml(),
    );
    $assert_session->pageTextContains('Test document');
    $assert_session->pageTextNotContains('Field with attached file');
    $assert_session->elementNotExists('css', 'iframe');

    // A user with sufficient permissions can see the preview button.
    $account = $this->drupalCreateUser([
      'access content',
      'preview document in collabora',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet($media->toUrl());
    $assert_session->pageTextContains('Field with attached file');
    $iframe = $assert_session->elementExists('css', 'iframe.cool-iframe');

    // Assert iframe attributes and dimensions.
    $this->assertSame('/cool/view/' . $media->id(), $iframe->getAttribute('src'));
    $this->assertSame('aspect-ratio: 5 / 2', $iframe->getAttribute('style'));
    $this->assertIframeDimensions();
  }

  /**
   * Asserts iframe dimensions and position.
   *
   * This verifies that the CSS is correctly applied.
   */
  protected function assertIframeDimensions(): void {
    [$iframe_box, $parent_box] = $this->getSession()->evaluateScript(<<<JS
[
  document.querySelector('iframe').getBoundingClientRect(),
  document.querySelector('iframe').parentElement.getBoundingClientRect(),
]
JS);

    // The iframe size and position is exactly as its parent element.
    $this->assertSame($parent_box, $iframe_box);

    // The aspect ratio is as configured.
    $this->assertEqualsWithDelta(2.5, $iframe_box['width'] / $iframe_box['height'], .01);
  }

}
