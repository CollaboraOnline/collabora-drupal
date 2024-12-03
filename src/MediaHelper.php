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

namespace Drupal\collabora_online;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Service to assist with media entities.
 */
class MediaHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Gets the file referenced by a media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media entity.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if not found.
   */
  public function getFile(Media $media) {
    $fid = $media->getSource()->getSourceFieldValue($media);
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fid);

    return $file;
  }

  /**
   * Gets a file based on the media id.
   *
   * @param int|string $id
   *   Media id which might be in strong form like '123'.
   *
   * @return \Drupal\file\FileInterface|null
   *   File referenced by the media entity, or NULL if not found.
   */
  public function getFileById($id) {
    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $this->entityTypeManager->getStorage('media')->load($id);
    return $this->getFile($media);
  }

  /**
   * Sets the file entity reference for a media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media entity to be modified.
   * @param \Drupal\file\Entity\File $source
   *   File entity to reference.
   */
  public function setMediaSource(Media $media, File $source) {
    $name = $media->getSource()->getSourceFieldDefinition($media->bundle->entity)->getName();
    $media->set($name, $source);
  }

}
