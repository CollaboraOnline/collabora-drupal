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
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Service to assist with media entities.
 */
class MediaHelper implements MediaHelperInterface {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFileForMedia(MediaInterface $media): ?FileInterface {
    $fid = $media->getSource()->getSourceFieldValue($media);
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fid);

    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function setMediaSource(MediaInterface $media, FileInterface $file): void {
    $field_name = $media->getSource()->getSourceFieldDefinition($media->bundle->entity)->getName();
    $media->set($field_name, $file);
  }

}
