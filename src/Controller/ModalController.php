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

namespace Drupal\collabora_online\Controller;

use Drupal\collabora_online\CollaboraUrl;
use Drupal\Component\Render\MarkupInterface;
use Drupal\media\MediaInterface;

/**
 * Provides a route response for the modal dialog content.
 */
class ModalController {

  /**
   * Title callback for the modal preview.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity from url.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   The title to show on top of the modal.
   */
  public function modalPreviewTitle(MediaInterface $media): string|MarkupInterface|null {
    return $media->label();
  }

  /**
   * Returns content for a modal preview.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity from url.
   *
   * @return array
   *   Render element.
   */
  public function modalPreview(MediaInterface $media): array {
    $url = CollaboraUrl::previewMedia($media);

    $iframe = [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => $url->toString(),
        'class' => ['cool-iframe'],
      ],
    ];
    $iframe['#attached']['library'][] = 'collabora_online/iframe';
    return $iframe;
  }

}
