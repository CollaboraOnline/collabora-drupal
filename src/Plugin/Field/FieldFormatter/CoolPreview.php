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

namespace Drupal\collabora_online\Plugin\Field\FieldFormatter;

use Drupal\collabora_online\CollaboraUrl;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'collabora_preview' formatter.
 */
#[FieldFormatter(
  id: 'collabora_preview',
  label: new TranslatableMarkup('Collabora Online preview'),
  field_types: [
    'file',
  ],
)]
class CoolPreview extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Preview Collabora Online documents.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    // Entity types other than 'media' are not supported.
    if ($field_definition->getTargetEntityTypeId() !== 'media') {
      return FALSE;
    }
    // Collabora online only supports one file per media.
    return !$field_definition->getFieldStorageDefinition()->isMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    /** @var \Drupal\media\MediaInterface $media */
    $media = $items->getEntity();

    $access_result = $media->access('preview in collabora', NULL, TRUE);
    (new CacheableMetadata())
      ->addCacheableDependency($access_result)
      ->applyTo($elements);

    if (!$access_result->isAllowed()) {
      return $elements;
    }

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $url = CollaboraUrl::previewMedia($media);

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
