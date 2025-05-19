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
use Drupal\Tests\RandomGeneratorTrait;

/**
 * Contains a method to create a test document.
 */
trait TestDocumentTrait {

  use RandomGeneratorTrait;

  /**
   * A media entity to test with.
   */
  protected MediaInterface $media;

  /**
   * Enables a standalone media page.
   *
   * This must be called before ->visitMediaPage().
   */
  protected function enableStandaloneMediaPage(): void {
    \Drupal::configFactory()
      ->getEditable('media.settings')
      ->set('standalone_url', TRUE)
      ->save(TRUE);

    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Creates a test document, and sets it in a property.
   *
   * This must be called before ->visitMediaPage().
   */
  protected function createTestDocument(): void {
    file_put_contents('public://file.txt', $this->randomString());
    $file = File::create(['uri' => 'public://test.txt']);
    $file->save();

    $this->media = Media::create([
      'name' => 'Test document',
      'bundle' => 'document',
      'field_media_file' => $file->id(),
    ]);
    $this->media->save();
  }

  /**
   * Visits the media page, and verifies that the media is shown.
   */
  protected function visitMediaPage(): void {
    $this->drupalGet($this->media->toUrl());
    $assert_session = $this->assertSession();
    $this->assertSame(
      'Test document | Drupal',
      $assert_session->elementExists('css', 'title')
        ->getHtml(),
    );
    $assert_session->pageTextContains('Test document');
  }

}
