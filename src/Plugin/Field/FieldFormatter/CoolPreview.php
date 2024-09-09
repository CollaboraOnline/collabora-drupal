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

use Drupal\collabora_online\Cool\CoolUtils;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

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
        $element = [];

        foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
            $media = CoolUtils::getMediaSourceForFile($file);
            if (!$media) {
                continue;
            }

            $url = CoolUtils::getEditorUrl($media, false);

            $render_array = [
                '#editorUrl' => $url,
                '#fileName' => $media->getName(),
            ];
            $render_array['#theme'] = 'collabora_online_preview';
            $render_array['#attached']['library'][] = 'collabora_online/cool.previewer';
            // Render each element as markup.
            $element[$delta] = $render_array;
        }
        return $element;
    }
}
