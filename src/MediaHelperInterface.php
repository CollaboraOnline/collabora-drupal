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

use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Service to assist with media entities.
 *
 * @internal
 *   This interface may evolve and change between major versions.
 */
interface MediaHelperInterface {

  /**
   * Gets the file referenced by a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if not found.
   */
  public function getFileForMedia(MediaInterface $media): ?FileInterface;

  /**
   * Gets a file based on the media id.
   *
   * @param int|string $id
   *   Media id which might be in string form like '123'.
   *
   * @return \Drupal\file\FileInterface|null
   *   File referenced by the media entity, or NULL if not found.
   */
  public function getFileForMediaId(int|string $id): ?FileInterface;

  /**
   * Sets the file entity reference for a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity to be modified.
   * @param \Drupal\file\FileInterface $file
   *   File entity to reference.
   */
  public function setMediaSource(MediaInterface $media, FileInterface $file): void;

}
