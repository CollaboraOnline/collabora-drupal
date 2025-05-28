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

namespace Drupal\Tests\collabora_online\Traits;

use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Trait with methods related to field formatters.
 *
 * This assumes that the media type and fields from 'collabora_online_test'
 * module are used.
 */
trait MediaFormatterTrait {

  /**
   * Sets the formatter for the 'field_media_file' field.
   *
   * @param string $formatter
   *   Formatter machine name.
   * @param array $settings
   *   Formatter settings.
   */
  protected function setFormatter(string $formatter, array $settings): void {
    EntityViewDisplay::load('media.document.default')
      ->setComponent('field_media_file', [
        'type' => $formatter,
        'label' => 'above',
        'settings' => $settings,
      ])
      ->save();
  }

  /**
   * Asserts formatter settings for the media file field.
   *
   * @param string $formatter
   *   Expected formatter machine name.
   * @param array|null $settings
   *   Expected settings for this formatter, or NULL to ignore.
   */
  protected function assertFormatter(string $formatter, array|null $settings = NULL): void {
    \Drupal::configFactory()->clearStaticCache();
    $actual = EntityViewDisplay::load('media.document.default')
      ->getComponent('field_media_file');
    $this->assertSame($formatter, $actual['type']);
    if ($settings !== NULL) {
      $this->assertSame($settings, $actual['settings']);
    }
  }

}
