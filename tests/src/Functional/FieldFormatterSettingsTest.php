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

use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\collabora_online\Traits\MediaFormatterTrait;

/**
 * Tests the Collabora configuration.
 *
 * @coversDefaultClass \Drupal\collabora_online\Form\ConfigForm
 */
class FieldFormatterSettingsTest extends BrowserTestBase {

  use MediaFormatterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'field_ui',
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

    FieldConfig::load('media.document.field_media_file')
      // Set a more distinctive field name.
      ->set('label', 'Field with attached file')
      ->save();

    $account = $this->drupalCreateUser([
      'administer media display',
    ]);
    $this->drupalLogin($account);

    $this->setFormatter('file_default', []);
  }

  /**
   * Tests the configuration for the Collabora settings form.
   *
   * @covers \Drupal\collabora_online\Plugin\Field\FieldFormatter\CollaboraPreviewEmbed::settingsForm
   */
  public function testIframeFormatterSettingsForm(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('/admin/structure/media/manage/document/display');

    $tr = $assert_session->elementExists('xpath', '//td[text() = "Field with attached file"]')->getParent();
    $select = $assert_session->selectExists('Plugin for Field with attached file', $tr);
    $this->assertSame('file_default', $select->getValue());

    $select->selectOption('Collabora Online preview embed');
    $assert_session->buttonExists('Save')->press();
    $this->assertFormatter('collabora_preview_embed', ['aspect_ratio' => NULL]);

    $assert_session->elementExists('css', '#edit-fields-field-media-file-settings-edit', $tr)->press();
    $aspect_ratio_field = $assert_session->fieldExists('Iframe aspect ratio', $tr);
    $this->assertSame('', $aspect_ratio_field->getValue());

    $aspect_ratio_field->setValue('abc');
    $assert_session->buttonExists('Update', $tr)->press();
    $assert_session->statusMessageContains('Iframe aspect ratio field is not in the right format.');

    $aspect_ratio_field->setValue('17 / 11');
    $assert_session->buttonExists('Update', $tr)->press();
    $assert_session->buttonExists('Save')->press();
    $this->assertFormatter('collabora_preview_embed', ['aspect_ratio' => '17 / 11']);
  }

}
