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
   * @param array $values
   *   Values for the media entity.
   *
   * @return \Drupal\media\MediaInterface
   *   New media entity.
   */
  protected function createMediaEntity(string $type, array $values = []): MediaInterface {
    file_put_contents('public://test.txt', 'Hello test');
    $file = File::create([
      'uri' => 'public://test.txt',
    ]);
    $file->save();
    $values += [
      'bundle' => $type,
      'field_media_file' => $file->id(),
    ];
    $media = Media::create($values);
    $media->save();

    return $media;
  }

}
