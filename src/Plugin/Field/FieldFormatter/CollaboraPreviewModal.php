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
use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Provide a field formatter with a modal preview.
 */
#[FieldFormatter(
  id: 'collabora_preview_modal',
  label: new TranslatableMarkup('Collabora Online preview modal'),
  field_types: [
    'file',
  ],
)]
class CollaboraPreviewModal extends CollaboraFileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $defaults = parent::defaultSettings();
    $defaults['max_width'] = NULL;
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $max_width = $this->getSetting('max_width');
    $summary[] = $this->t('Maximum dialog width: @max_width', [
      '@max_width' => match ($max_width) {
        // The value can temporarily be '' instead of NULL.
        // It will be replaced with NULL on save, thanks to the schema.
        NULL, '' => $this->t('Default (880px)'),
        default => $max_width . 'px',
      },
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['max_width'] = [
      '#title' => $this->t('Maximum dialog width'),
      '#type' => 'number',
      '#field_suffix' => 'px',
      '#min' => 30,
      '#max' => 3000,
      '#default_value' => $this->getSetting('max_width'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewElement(MediaInterface $media, FileInterface $file): array {
    $url = CollaboraUrl::mediaModalPreview($media);
    /** @var string|int|null $max_width_setting */
    $max_width_setting = $this->getSetting('max_width');
    $max_width = match ($max_width_setting) {
      // The max_width setting can be a string during views preview, when the
      // schema normalization to int|null has not been applied yet.
      NULL, '' => 880,
      default => (int) $max_width_setting,
    };
    return [
      '#type' => 'container',
      '#attached' => ['library' => ['collabora_online/modal_preview']],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Preview'),
        '#url' => $url,
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--small'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => $max_width,
            'classes' => [
              'ui-dialog' => 'cool-modal-preview',
            ],
          ]),
        ],
        '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      ],
    ];
  }

}
