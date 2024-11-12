<?php

declare(strict_types=1);

namespace Drupal\collabora_online\Plugin\views\field;

use Drupal\collabora_online\Cool\CoolUtils;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\views\ResultRow;

/**
 * Field handler for link to edit a collabora file.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("media_collabora_edit")
 */
class CollaboraEdit extends LinkBase {

  protected function getUrlInfo(ResultRow $row): Url|NULL {
    /** @var \Drupal\media\MediaInterface $entity */
    $entity = $this->getEntity($row);

    if ($entity === NULL) {
      return NULL;
    }

    return CoolUtils::getEditorUrl($entity, TRUE);
  }

}
