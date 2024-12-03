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

namespace Drupal\collabora_online\Plugin\Field\FieldFormatter;

use Drupal\collabora_online\CoolUrl;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\media\MediaInterface;

/**
 * Plugin implementation of the 'collabora_preview' formatter.
 *
 * @FieldFormatter(
 *   id = "collabora_preview",
 *   label = @Translation("Collabora Online preview"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class CoolPreview extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Preview Collabora Online documents.');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    $elements = [];
    $media = $items->getEntity();
    if (!$media instanceof MediaInterface) {
      // Entity types other than 'media' are not supported.
      return [];
    }

    $access_result = $media->access('preview in collabora', NULL, TRUE);
    (new CacheableMetadata())
      ->addCacheableDependency($access_result)
      ->applyTo($elements);

    if (!$access_result->isAllowed()) {
      return $elements;
    }

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $url = CoolUrl::getEditorUrl($media, FALSE);

      $render_array = [
        '#editorUrl' => $url,
        '#fileName' => $media->getName(),
      ];
      $render_array['#theme'] = 'collabora_online_preview';
      $render_array['#attached']['library'][] = 'collabora_online/cool.previewer';
      // Render each element as markup.
      $elements[$delta] = $render_array;
    }
    return $elements;
  }

}
