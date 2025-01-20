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

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Provides methods to create a media from given values.
 */
trait MediaCreationTrait {

  /**
   * Creates a media entity with attached file.
   *
   * @param string $type
   *   Media type.
   * @param array $media_values
   *   Values for the media entity.
   * @param array $file_values
   *   Values for the file entity.
   *   This should not contain 'uri'.
   *
   * @return \Drupal\media\MediaInterface
   *   New media entity.
   */
  protected function createMediaEntity(string $type, array $media_values = [], array $file_values = []): MediaInterface {
    file_put_contents('public://test.txt', 'Hello test');
    $this->assertArrayNotHasKey('uri', $file_values);
    $file_values += [
      'uri' => 'public://test.txt',
    ];
    $file = File::create($file_values);
    $file->save();
    $media_values += [
      'bundle' => $type,
      'field_media_file' => $file->id(),
    ];
    $media = Media::create($media_values);
    $media->save();

    return $media;
  }

}
