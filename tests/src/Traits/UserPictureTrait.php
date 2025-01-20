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

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;

/**
 * Provides methods to add a user picture.
 *
 * @see \Drupal\Tests\image\Kernel\Views\RelationshipUserImageDataTest
 */
trait UserPictureTrait {

  /**
   * Creates the user picture field as in standard profile.
   */
  public function createUserPictureField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'user',
      'field_name' => 'user_picture',
      'type' => 'image',
      'translatable' => '0',
    ])->save();
    FieldConfig::create([
      'label' => 'User Picture',
      'description' => '',
      'field_name' => 'user_picture',
      'entity_type' => 'user',
      'bundle' => 'user',
      'required' => 0,
    ])->save();
  }

  /**
   * Creates a picture file and attaches it to a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   User entity.
   *
   * @return \Drupal\file\FileInterface
   *   Picture file entity.
   */
  public function createUserPicture(UserInterface $user): FileInterface {
    $root = $this->getDrupalRoot();
    copy($root . '/core/misc/druplicon.png', 'public://user-picture.png');
    $this->assertFileExists('public://user-picture.png');
    $file = File::create([
      'uid' => $user->id(),
      'filename' => 'user-picture.png',
      'uri' => "public://user-picture.png",
      'filemime' => 'image/png',
    ]);
    $file->save();
    $user->set('user_picture', $file->id());
    $user->save();
    return $file;
  }

}
