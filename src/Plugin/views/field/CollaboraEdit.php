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

namespace Drupal\collabora_online\Plugin\views\field;

use Drupal\collabora_online\CoolUrl;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\views\ResultRow;

/**
 * Field handler for link to edit a collabora file.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField('media_collabora_edit')]
class CollaboraEdit extends LinkBase {

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row): Url|null {
    /** @var \Drupal\media\MediaInterface $entity */
    $entity = $this->getEntity($row);

    if ($entity === NULL) {
      return NULL;
    }

    return CoolUrl::editMedia($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel(): TranslatableMarkup {
    return $this->t('Edit in Collabora Online');
  }

}
