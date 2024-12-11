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

namespace Drupal\Tests\collabora_online\Kernel;

use Drupal\collabora_online\MediaHelperInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Tests the MediaHelper service.
 *
 * @coversDefaultClass \Drupal\collabora_online\MediaHelper
 */
class MediaHelperTest extends CollaboraKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->createMediaType('file', ['id' => 'document']);
  }

  /**
   * Tests all methods at once, for performance reasons.
   *
   * @covers \Drupal\collabora_online\MediaHelper::getFileForMedia()
   * @covers \Drupal\collabora_online\MediaHelper::getFileForMediaId()
   * @covers \Drupal\collabora_online\MediaHelper::setMediaSource()
   */
  public function testMediaHelper(): void {
    /** @var \Drupal\collabora_online\MediaHelperInterface $helper */
    $helper = \Drupal::service(MediaHelperInterface::class);

    file_put_contents('public://test.txt', 'Hello test');
    $file = File::create([
      'uri' => 'public://test.txt',
    ]);
    $file->save();

    $media = Media::create([
      'bundle' => 'document',
      'field_media_file' => $file->id(),
    ]);
    $media->save();

    $this->assertEquals(
      $file->id(),
      $helper->getFileForMedia($media)->id(),
    );

    $this->assertEquals(
      $file->id(),
      $helper->getFileForMediaId($media->id())->id(),
    );

    file_put_contents('public://test1.txt', 'Hello test 1');
    $other_file = File::create([
      'uri' => 'public://test1.txt',
    ]);
    $other_file->save();

    $helper->setMediaSource($media, $other_file);

    $this->assertEquals(
      $other_file->id(),
      $media->get('field_media_file')->getValue()[0]['target_id'],
    );

    // The first method reads the file id from the existing media instance,
    // which already has the new value.
    $this->assertEquals(
      $other_file->id(),
      $helper->getFileForMedia($media)->id(),
    );
    // The other method loads a fresh media entity instance, which still has the
    // old value.
    // The test documents this behavior, whether it is desirable or not.
    $this->assertEquals(
      $file->id(),
      $helper->getFileForMediaId($media->id())->id(),
    );

    $media->save();

    // After saving, both methods return the new file.
    $this->assertEquals(
      $other_file->id(),
      $helper->getFileForMedia($media)->id(),
    );
    $this->assertEquals(
      $other_file->id(),
      $helper->getFileForMediaId($media->id())->id(),
    );
  }

}
