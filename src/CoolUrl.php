<?php

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
