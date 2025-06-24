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
    /** @var int|string|null $fid */
    $fid = $media->getSource()->getSourceFieldValue($media);
    if ($fid === NULL) {
      // The media entity does not have a file attached.
      return NULL;
    }
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fid);

    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function setMediaSource(MediaInterface $media, FileInterface $file): void {
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $media->get('bundle')->entity;
    $source_field = $media->getSource()->getSourceFieldDefinition($media_type);
    if ($source_field === NULL) {
      // Throw an unhandled exception for now.
      // @todo Throw a handled exception, and catch it in the calling code.
      throw new \InvalidArgumentException(sprintf(
        'The media type %s of media %s is not supported, because it does not have a source field.',
        $media_type->id(),
        $media->id(),
      ));
    }
    $field_name = $source_field->getName();
    $media->set($field_name, $file);
  }

}
