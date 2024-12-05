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

use Drupal\file\Entity\File;

/**
 * Class with various static methods.
 */
class CoolUtils {

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

}
