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
   * Gets a url to open media in Collabora as read-only.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity that holds the file to open in the viewer.
   *
   * @return \Drupal\Core\Url
   *   Editor url to visit as full-page, or to embed in an iframe.
   */
  public static function previewMedia(MediaInterface $media): Url {
    return Url::fromRoute('collabora-online.view', ['media' => $media->id()]);
  }

  /**
   * Gets a url to open media in Collabora in edit mode.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity that holds the file to open in the editor.
   *
   * @return \Drupal\Core\Url
   *   Editor url to visit as full-page, or to embed in an iframe.
   */
  public static function editMedia(MediaInterface $media): Url {
    return Url::fromRoute('collabora-online.edit', ['media' => $media->id()]);
  }

}
