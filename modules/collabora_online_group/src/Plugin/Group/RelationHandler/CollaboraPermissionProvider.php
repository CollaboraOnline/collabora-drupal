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

use Drupal\groupmedia\Plugin\Group\RelationHandler\GroupMediaPermissionProvider;

/**
 * Provides Collabora permissions for group.
 */
class CollaboraPermissionProvider extends GroupMediaPermissionProvider {

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(): array {
    $permissions = $this->parent->buildPermissions();

    /* @see \Drupal\group\Plugin\Group\RelationHandlerDefault\PermissionProvider::buildPermissions() */
    $provider_chain = $this->groupRelationTypeManager()->getPermissionProvider($this->pluginId);

    // Add Collabora permissions.
    $prefix = 'Entity:';
    if ($name = $provider_chain->getPermission('preview in collabora', 'entity')) {
      $permissions[$name] = $this->buildPermission("$prefix Preview published %entity_type in collabora");
    }
    if ($name = $provider_chain->getPermission('preview in collabora unpublished', 'entity', 'own')) {
      $permissions[$name] = $this->buildPermission("$prefix Preview own unpublished %entity_type in collabora");
    }
    if ($name = $provider_chain->getPermission('edit in collabora', 'entity')) {
      $permissions[$name] = $this->buildPermission("$prefix Edit any %entity_type in collabora");
    }
    if ($name = $provider_chain->getPermission('edit in collabora', 'entity', 'own')) {
      $permissions[$name] = $this->buildPermission("$prefix Edit own %entity_type in collabora");
    }

    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermission($operation, $target, $scope = 'any'): bool|string {
    if (
      $target === 'entity' &&
      $this->definesEntityPermissions &&
      ($this->implementsOwnerInterface || $scope === 'any')
    ) {
      switch ($operation) {
        case 'preview in collabora':
          if ($scope === 'any') {
            return "preview $this->pluginId in collabora";
          }

          return FALSE;

        case 'preview in collabora unpublished':
          if ($this->implementsPublishedInterface && $scope === 'own') {
            return "preview $scope unpublished $this->pluginId in collabora";
          }

          return FALSE;

        case 'edit in collabora':
          return "edit $scope $this->pluginId in collabora";
      }
    }

    return $this->parent->getPermission($operation, $target, $scope);
  }

}
