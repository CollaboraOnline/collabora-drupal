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

namespace Drupal\Tests\collabora_online_group\Kernel;

use Drupal\group\PermissionScopeInterface;
use Drupal\Tests\collabora_online\Traits\MediaCreationTrait;
use Drupal\Tests\collabora_online_group\Traits\GroupRelationTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\RoleInterface;

/**
 * Tests Collabora group access.
 */
class AccessTest extends GroupKernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;
  use MediaCreationTrait;
  use GroupRelationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'media',
    'image',
    'group',
    'groupmedia',
    'collabora_online',
    'collabora_online_group',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('group_role');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Tests that access to Collabora group permissions is handled.
   */
  public function testCollaboraAccess(): void {
    // Create group type, media type and enable plugin.
    $group_type = $this->createGroupType();
    $group_role = $this->createGroupRole([
      'group_type' => $group_type->id(),
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => [],
    ]);
    $this->createMediaType('file', ['id' => 'document']);
    $this->createPluginRelation($group_type, 'group_media:document', [
      'group_cardinality' => 0,
      'entity_cardinality' => 1,
      'use_creation_wizard' => FALSE,
    ]);

    // Add group, media and relation between both.
    $group = $this->createGroup(['type' => $group_type->id()]);
    $media = $this->createMediaEntity('document');
    $group->addRelationship($media, 'group_media:document');

    // Iterate over each scenario.
    foreach ($this->getTestScenarios() as $scenario_name => $scenario) {
      // Apply status to media.
      $media->set('status', $scenario['status'])->save();
      // Set the current permissions for the existing role.
      $group_role->set('permissions', $scenario['group_permissions'])->save();
      // Create the user with the given permissions and as member of the
      // group.
      $user = $this->createUser($scenario['permissions']);
      $group->addMember($user);
      // Set user as owner if the scope is 'own'.
      $owner = $scenario['scope'] === 'own' ? $user->id() : 0;
      $media->setOwnerId($owner)->save();

      // Check access.
      $this->assertEquals(
        $scenario['result'],
        $media->access($scenario['operation'], $user),
        sprintf('Access check failed for scenario: "%s"', $scenario_name)
      );
    }
  }

  /**
   * Retrieves the scenarios to be tested.
   *
   * @return array
   *   An array of test scenarios.
   */
  protected function getTestScenarios(): array {
    // The scenario keys contains values used for each scenario:
    // 'operation:status:scope:global_permission:group_permission'.
    return [
      // Preview no permissions cases.
      'preview:published:any::' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => [],
        'operation' => 'preview in collabora',
        'status' => 1,
        'scope' => 'any',
      ],
      'preview:published:own::' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => [],
        'operation' => 'preview in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      // The global permissions that would allow to preview, doesn't work
      // in a media related to a group.
      'preview:published:any:preview:' => [
        'result' => FALSE,
        'permissions' => ['preview document in collabora'],
        'group_permissions' => [],
        'operation' => 'preview in collabora',
        'status' => 1,
        'scope' => 'any',
      ],
      'preview:published:own:preview:' => [
        'result' => FALSE,
        'permissions' => ['preview document in collabora'],
        'group_permissions' => [],
        'operation' => 'preview in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      // User can only see published entities with the group preview
      // permission.
      'preview:published:any::preview' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['preview group_media:document in collabora'],
        'operation' => 'preview in collabora',
        'status' => 1,
        'scope' => 'any',
      ],
      'preview:published:own::preview' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['preview group_media:document in collabora'],
        'operation' => 'preview in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      'preview:unpublished:any::preview' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => ['preview group_media:document in collabora'],
        'operation' => 'preview in collabora',
        'status' => 0,
        'scope' => 'any',
      ],
      'preview:unpublished:own::preview' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => ['preview group_media:document in collabora'],
        'operation' => 'preview in collabora',
        'status' => 0,
        'scope' => 'own',
      ],
      // The global preview unpublished doesn't affect to medias related
      // to a group.
      'preview:unpublished:own:preview_own_unpublished:' => [
        'result' => FALSE,
        'permissions' => ['preview own unpublished document in collabora'],
        'group_permissions' => [],
        'operation' => 'preview in collabora',
        'status' => 0,
        'scope' => 'own',
      ],
      // The group permission to preview own unpublished permission allows
      // to see only entities with such properties.
      'preview:published:any::preview_own_unpublished' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => ['preview own unpublished group_media:document in collabora'],
        'operation' => 'preview in collabora',
        'status' => 1,
        'scope' => 'any',
      ],
      'preview:published:own::preview_own_unpublished' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => ['preview own unpublished group_media:document in collabora'],
        'operation' => 'preview in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      'preview:unpublished:own::preview_own_unpublished' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['preview own unpublished group_media:document in collabora'],
        'operation' => 'preview in collabora',
        'status' => 0,
        'scope' => 'own',
      ],
      'preview:unpublished:any::preview_own_unpublished' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => ['preview own unpublished group_media:document in collabora'],
        'operation' => 'preview in collabora',
        'status' => 0,
        'scope' => 'any',
      ],
      // Edit no permissions cases.
      'edit:published:any::' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => [],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'any',
      ],
      'edit:published:own::' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => [],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      // The global permission doesn't grant access to edit in a group.
      'edit:published:any:edit_any:' => [
        'result' => FALSE,
        'permissions' => ['edit any document in collabora'],
        'group_permissions' => [],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'any',
      ],
      'edit:published:own:edit_any:' => [
        'result' => FALSE,
        'permissions' => ['edit any document in collabora'],
        'group_permissions' => [],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      'edit:published:own:edit_own:' => [
        'result' => FALSE,
        'permissions' => ['edit own document in collabora'],
        'group_permissions' => [],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      // Only users with edit any permission in a group can edit all.
      'edit:published:any::edit_any' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['edit any group_media:document in collabora'],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'any',
      ],
      'edit:published:own::edit_any' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['edit any group_media:document in collabora'],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      'edit:unpublished:any::edit_any' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['edit any group_media:document in collabora'],
        'operation' => 'edit in collabora',
        'status' => 0,
        'scope' => 'any',
      ],
      'edit:unpublished:own::edit_any' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['edit any group_media:document in collabora'],
        'operation' => 'edit in collabora',
        'status' => 0,
        'scope' => 'own',
      ],
      // Or edit own permission for the entities the user owns.
      'edit:published:own::edit_own' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['edit own group_media:document in collabora'],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'own',
      ],
      'edit:unpublished:own::edit_own' => [
        'result' => TRUE,
        'permissions' => [],
        'group_permissions' => ['edit own group_media:document in collabora'],
        'operation' => 'edit in collabora',
        'status' => 0,
        'scope' => 'own',
      ],
      'edit:published:any::edit_own' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => ['edit own group_media:document in collabora'],
        'operation' => 'edit in collabora',
        'status' => 1,
        'scope' => 'any',
      ],
      'edit:unpublished:any::edit_own' => [
        'result' => FALSE,
        'permissions' => [],
        'group_permissions' => ['edit own group_media:document in collabora'],
        'operation' => 'edit in collabora',
        'status' => 0,
        'scope' => 'any',
      ],
    ];
  }

}
