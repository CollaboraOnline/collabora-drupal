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

use Drupal\Core\Url;
use Drupal\media\MediaInterface;

/**
 * Static methods to build urls.
 */
class CoolUrl {

  /**
   * Gets the editor / viewer Drupal URL from the routes configured.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity that holds the file to open in the editor.
   * @param bool $can_write
   *   TRUE for an edit url, FALSE for a read-only preview url.
   *
   * @return \Drupal\Core\Url
   *   Editor url to visit as full-page, or to embed in an iframe.
   */
  public static function getEditorUrl(MediaInterface $media, $can_write = FALSE) {
    if ($can_write) {
      return Url::fromRoute('collabora-online.edit', ['media' => $media->id()]);
    }
    else {
      return Url::fromRoute('collabora-online.view', ['media' => $media->id()]);
    }
  }

}
