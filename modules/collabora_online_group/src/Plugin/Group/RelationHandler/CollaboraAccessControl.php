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

namespace Drupal\collabora_online_group\Plugin\Group\RelationHandler;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlTrait;
use Drupal\group\Plugin\Group\RelationHandlerDefault\AccessControl;

/**
 * Provides access control for collabora group.
 */
class CollaboraAccessControl extends AccessControl {

  use AccessControlTrait;

  /**
   * Constructs a new CollaboraAccessControl.
   *
   * @param \Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface $parent
   *   The default access control.
   */
  public function __construct(AccessControlInterface $parent) {
    $this->parent = $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account, $return_as_object = FALSE): AccessResultInterface|bool {
    // Add support for unpublished operation: preview in collabora.
    $check_published = $operation === 'preview in collabora' && $this->implementsPublishedInterface;

    if ($check_published) {
      // The $this->implementsPublishedInterface property indicates that
      // entities of this type implement EntityPublishedInterface.
      /* @see \Drupal\group\Plugin\Group\RelationHandlerDefault\AccessControl::entityAccess() */
      assert($entity instanceof EntityPublishedInterface);

      if (!$entity->isPublished()) {
        $operation .= ' unpublished';
      }
    }

    assert($this->parent !== NULL);
    return $this->parent->entityAccess($entity, $operation, $account, $return_as_object);
  }

}
