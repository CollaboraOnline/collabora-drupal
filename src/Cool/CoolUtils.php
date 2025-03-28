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
   * Determines if a MIME type is supported for editing.
   *
   * @param string $mimetype
   *   File MIME type.
   *
   * @return bool
   *   TRUE if the MIME type is supported for editing.
   *   FALSE if the MIME type can only be opened as read-only.
   */
  public static function canEditMimeType(string $mimetype) {
    return !array_key_exists($mimetype, static::READ_ONLY);
  }

}
