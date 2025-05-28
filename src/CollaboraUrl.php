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
 *
 * The main purpose is not having to remember route names and route parameter
 * names, which then cannot be verified by static analysis.
 */
class CollaboraUrl {

  /**
   * Gets a url to open media in Collabora as read-only.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity that holds the file to open in the viewer.
   * @param array $options
   *   See \Drupal\Core\Url::fromUri() for details.
   *
   * @return \Drupal\Core\Url
   *   Editor url to visit as full-page, or to embed in an iframe.
   */
  public static function previewMedia(MediaInterface $media, array $options = []): Url {
    return Url::fromRoute('collabora-online.view', ['media' => $media->id()], $options);
  }

  /**
   * Gets a url to open media in Collabora in edit mode.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity that holds the file to open in the editor.
   * @param array $options
   *   See \Drupal\Core\Url::fromUri() for details.
   *
   * @return \Drupal\Core\Url
   *   Editor url to visit as full-page, or to embed in an iframe.
   */
  public static function editMedia(MediaInterface $media, array $options = []): Url {
    return Url::fromRoute('collabora-online.edit', ['media' => $media->id()], $options);
  }

}
