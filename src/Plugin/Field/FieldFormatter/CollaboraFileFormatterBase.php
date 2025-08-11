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

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Base class for Collabora Online field formatters.
 */
abstract class CollaboraFileFormatterBase extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    // Entity types other than 'media' are not supported.
    if ($field_definition->getTargetEntityTypeId() !== 'media') {
      return FALSE;
    }
    // Collabora online only supports one file per media.
    if ($field_definition->getFieldStorageDefinition()->isMultiple()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    assert($items instanceof EntityReferenceFieldItemListInterface);
    $elements = [];
    $media = $items->getEntity();
    assert($media instanceof MediaInterface);

    $access_result = $media->access('preview in collabora', NULL, TRUE);
    (new CacheableMetadata())
      ->addCacheableDependency($access_result)
      ->applyTo($elements);

    if (!$access_result->isAllowed()) {
      return $elements;
    }

    // This formatter is for single-value fields only.
    // We still use foreach(), to return the typical array structure expected
    // from a field formatter.
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      assert($file instanceof FileInterface);
      $elements[$delta] = $this->viewElement($media, $file);
    }

    return $elements;
  }

  /**
   * Builds the render element for the single field item.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param \Drupal\file\FileInterface $file
   *   The referenced file entity.
   *
   * @return array
   *   A field item render element.
   */
  abstract protected function viewElement(MediaInterface $media, FileInterface $file): array;

}
