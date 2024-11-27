<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online_group\Kernel;

use Drupal\Tests\collabora_online_group\Traits\GroupRelationTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests group permissions.
 */
class PermissionTest extends GroupKernelTestBase {

  use MediaTypeCreationTrait;
  use GroupRelationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'media',
    'group',
    'groupmedia',
  ];

  /**
   * Tests that group permissions are properly created.
   */
  public function testGroupPermissions(): void {
    // Generate types: groups and medias.
    $group_type_1 = $this->createGroupType();
    $group_type_2 = $this->createGroupType();
    $group_type_3 = $this->createGroupType();
    $this->createMediaType('file', ['id' => 'document']);
    $this->createMediaType('file', ['id' => 'spreadsheet']);

    // Enable relation plugins in groups.
    $this->createPluginRelation(
      $group_type_1,
      'group_media:document',
      [
        'group_cardinality' => 0,
        'entity_cardinality' => 1,
        'use_creation_wizard' => FALSE,
      ]);
    $this->createPluginRelation(
      $group_type_2,
      'group_media:document',
      [
        'group_cardinality' => 0,
        'entity_cardinality' => 1,
        'use_creation_wizard' => FALSE,
      ]);
    $this->createPluginRelation(
      $group_type_2,
      'group_media:spreadsheet',
      [
        'group_cardinality' => 0,
        'entity_cardinality' => 1,
        'use_creation_wizard' => FALSE,
      ]);

    // Check that permissions are generated for the groups.
    // Save current permissions.
    /** @var \Drupal\group\Access\GroupPermissionHandlerInterface $permission_handler */
    $permission_handler = \Drupal::service('group.permissions');
    $permissions_before_1 = $permission_handler->getPermissionsByGroupType($group_type_1);
    $permissions_before_2 = $permission_handler->getPermissionsByGroupType($group_type_2);
    $permissions_before_3 = $permission_handler->getPermissionsByGroupType($group_type_3);

    // Get permissions difference after enabling the module.
    $this->enableModules(['collabora_online_group']);
    $permission_handler = \Drupal::service('group.permissions');
    $permissions_after_1 = $permission_handler->getPermissionsByGroupType($group_type_1);
    $new_permissions_1 = array_diff_key($permissions_after_1, $permissions_before_1);
    ksort($new_permissions_1);
    $permissions_after_2 = $permission_handler->getPermissionsByGroupType($group_type_2);
    $new_permissions_2 = array_diff_key($permissions_after_2, $permissions_before_2);
    ksort($new_permissions_2);
    $permissions_after_3 = $permission_handler->getPermissionsByGroupType($group_type_3);
    $new_permissions_3 = array_diff_key($permissions_after_3, $permissions_before_3);
    ksort($new_permissions_3);

    // The 'group_1' has only 'document' permissions.
    $this->assertSame(
      [
        'edit any group_media:document in collabora' => 'Entity: Edit any <em class="placeholder">media item</em> in collabora',
        'edit own group_media:document in collabora' => 'Entity: Edit own <em class="placeholder">media item</em> in collabora',
        'preview group_media:document in collabora' => 'Entity: Preview published <em class="placeholder">media item</em> in collabora',
        'preview own unpublished group_media:document in collabora' => 'Entity: Preview own unpublished <em class="placeholder">media item</em> in collabora',
      ],
      array_map(
        fn($permission) => (string) $permission['title'],
        $new_permissions_1,
      ));
    // The 'group_2' has 'document' and 'spreadsheet' permissions.
    $this->assertSame(
      [
        'edit any group_media:document in collabora' => 'Entity: Edit any <em class="placeholder">media item</em> in collabora',
        'edit any group_media:spreadsheet in collabora' => 'Entity: Edit any <em class="placeholder">media item</em> in collabora',
        'edit own group_media:document in collabora' => 'Entity: Edit own <em class="placeholder">media item</em> in collabora',
        'edit own group_media:spreadsheet in collabora' => 'Entity: Edit own <em class="placeholder">media item</em> in collabora',
        'preview group_media:document in collabora' => 'Entity: Preview published <em class="placeholder">media item</em> in collabora',
        'preview group_media:spreadsheet in collabora' => 'Entity: Preview published <em class="placeholder">media item</em> in collabora',
        'preview own unpublished group_media:document in collabora' => 'Entity: Preview own unpublished <em class="placeholder">media item</em> in collabora',
        'preview own unpublished group_media:spreadsheet in collabora' => 'Entity: Preview own unpublished <em class="placeholder">media item</em> in collabora',
      ],
      array_map(
        fn($permission) => (string) $permission['title'],
        $new_permissions_2,
      ));
    // The 'group_3' doesn't have any new permissions.
    $this->assertSame(
      [],
      array_map(
        fn($permission) => (string) $permission['title'],
        $new_permissions_3,
      ));
  }

}
