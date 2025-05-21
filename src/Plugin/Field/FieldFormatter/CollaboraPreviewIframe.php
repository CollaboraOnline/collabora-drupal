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
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Provides a field formatter with a preview iframe.
 */
#[FieldFormatter(
  id: 'collabora_preview_iframe',
  label: new TranslatableMarkup('Collabora Online preview iframe'),
  field_types: [
    'file',
  ],
)]
class CollaboraPreviewIframe extends CollaboraFileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $defaults = parent::defaultSettings();
    $defaults['aspect_ratio'] = NULL;
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $aspect_ratio = $this->getSetting('aspect_ratio');
    $summary[] = $this->t('Iframe aspect ratio: @aspect_ratio', [
      '@aspect_ratio' => match ($aspect_ratio) {
        // The value can temporarily be '' instead of NULL.
        // It will be replaced with NULL on save, thanks to the schema.
        NULL, '' => $this->t('Default (3 / 2, overridable with CSS)'),
        default => $aspect_ratio,
      },
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['aspect_ratio'] = [
      '#title' => $this->t('Iframe aspect ratio'),
      '#type' => 'textfield',
      '#pattern' => '[1-9]\d* \/ [1-9]\d*',
      '#default_value' => $this->getSetting('aspect_ratio'),
      '#description' => $this->t("Aspect ratio as 'width / height', e.g. '3 / 2' or '16 / 9'."),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewElement(MediaInterface $media, FileInterface $file): array {
    $url = CollaboraUrl::previewMedia($media);
    $iframe = [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => $url->toString(),
        'class' => ['cool-iframe'],
      ],
    ];
    /** @var string|null $aspect_ratio_setting */
    $aspect_ratio_setting = $this->getSetting('aspect_ratio');
    // The aspect_ratio setting can be '' during views preview, when the schema
    // normalization to string|null has not been applied yet.
    if ($aspect_ratio_setting !== NULL && $aspect_ratio_setting !== '') {
      $iframe['#attributes']['style'] = 'aspect-ratio: ' . $aspect_ratio_setting;
    }
    $iframe['#attached']['library'][] = 'collabora_online/iframe';
    return $iframe;
  }

}
