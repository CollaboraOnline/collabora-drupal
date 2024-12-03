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

namespace Drupal\collabora_online\Cool;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Class with various static methods.
 */
class CoolUtils {

  /**
   * Gets the file referenced by a media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media entity.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if not found.
   */
  public static function getFile(Media $media) {
    $fid = $media->getSource()->getSourceFieldValue($media);
    $file = File::load($fid);

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
  public static function getFileById($id) {
    /** @var \Drupal\media\MediaInterface|null $media */
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($id);
    return CoolUtils::getFile($media);
  }

  /**
   * Sets the file entity reference for a media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media entity to be modified.
   * @param \Drupal\file\Entity\File $source
   *   File entity to reference.
   */
  public static function setMediaSource(Media $media, File $source) {
    $name = $media->getSource()->getSourceFieldDefinition($media->bundle->entity)->getName();
    $media->set($name, $source);
  }

  /**
   * List of read only formats. Currently limited to the one Drupal accept.
   */
  const READ_ONLY = [
    'application/x-iwork-keynote-sffkey' => TRUE,
    'application/x-iwork-pages-sffpages' => TRUE,
    'application/x-iwork-numbers-sffnumbers' => TRUE,
  ];

  /**
   * Determines if we can edit that media file.
   *
   * There are few types that Collabora Online only views.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   *
   * @return bool
   *   TRUE if the file has a file type that is supported for editing.
   *   FALSE if the file can only be opened as read-only.
   */
  public static function canEdit(File $file) {
    $mimetype = $file->getMimeType();
    return !array_key_exists($mimetype, static::READ_ONLY);
  }

  /**
   * Gets the editor / viewer Drupal URL from the routes configured.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media entity that holds the file to open in the editor.
   * @param bool $can_write
   *   TRUE for an edit url, FALSE for a read-only preview url.
   *
   * @return \Drupal\Core\Url
   *   Editor url to visit as full-page, or to embed in an iframe.
   */
  public static function getEditorUrl(Media $media, $can_write = FALSE) {
    if ($can_write) {
      return Url::fromRoute('collabora-online.edit', ['media' => $media->id()]);
    }
    else {
      return Url::fromRoute('collabora-online.view', ['media' => $media->id()]);
    }
  }

}
