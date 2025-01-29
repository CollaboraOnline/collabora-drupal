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

namespace Drupal\Tests\collabora_online_group\Functional;

use Drupal\Core\Url;
use Drupal\group\PermissionScopeInterface;
use Drupal\media\Entity\Media;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\collabora_online\Traits\MediaCreationTrait;
use Drupal\Tests\collabora_online_group\Traits\GroupRelationTrait;
use Drupal\Tests\group\Traits\GroupTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\RoleInterface;

/**
 * Test the modifications performed in groupmedia view by the module.
 */
class GroupMediaViewsTest extends BrowserTestBase {

  use MediaCreationTrait;
  use GroupRelationTrait;
  use GroupTestTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online_group',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // Suppress a schema error from groupmedia default config.
    // See https://www.drupal.org/project/groupmedia/issues/3491778.
    'views.view.group_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test the links for Collabora operations in the view.
   */
  public function testViewLinks(): void {
    // Add configuration needed for testing.
    $group_type = $this->createGroupType(['id' => 'group_type_1']);
    $this->createMediaType('file', [
      'id' => 'document',
      'label' => 'Document',
    ]);
    $this->createGroupRole([
      'group_type' => 'group_type_1',
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => [
        'view group',
        'access group_media overview',
        'view group_media:document entity',
        'edit any group_media:document in collabora',
        'preview group_media:document in collabora',
      ],
    ]);
    $this->createPluginRelation($group_type, 'group_media:document', [
      'group_cardinality' => 0,
      'entity_cardinality' => 1,
      'use_creation_wizard' => FALSE,
    ]);

    // Create content.
    $group = $this->createGroup(['type' => 'group_type_1']);
    for ($i = 0; $i < 3; $i++) {
      $media = $this->createMediaEntity('document', [
        'id' => 'media_' . $i,
        'name' => 'Media ' . $i,
      ]);
      $group->addRelationship($media, 'group_media:document');
    }
    $user = $this->createUser([
      'view the administration theme',
      'access administration pages',
      'access group overview',
    ]);
    $group->addMember($user);

    // Go to the page and check the links added to the view.
    $this->drupalLogin($user);
    $this->drupalGet("group/{$group->id()}/media");
    $assert_session = $this->assertSession();

    // Check table header.
    $table = $assert_session->elementExists('css', 'table');
    $table_header = $assert_session->elementExists('css', 'thead', $table);
    $rows = $table_header->findAll('css', 'tr');
    $cols = $rows[0]->findAll('css', 'th');
    $this->assertEquals('Media name', $cols[0]->getText());
    $this->assertEquals('Bundle', $cols[1]->getText());
    $this->assertEquals('Status', $cols[2]->getText());
    $this->assertEquals('Publisher', $cols[3]->getText());
    // Support different versions of groupmedia.
    $this->assertTrue(in_array($cols[4]->getText(), ['Operations', 'Dropbutton']));

    // Check that rows contain new links for operations in Collabora.
    $table_body = $assert_session->elementExists('css', 'tbody', $table);
    $rows = $table_body->findAll('css', 'tr');
    $i = 0;
    foreach (Media::loadMultiple() as $media) {
      $cols = $rows[$i]->findAll('css', 'td');
      $this->assertEquals('Media ' . $i, $cols[0]->getText());
      $this->assertEquals('Document', $cols[1]->getText());
      $this->assertEquals('Yes', $cols[2]->getText());
      $this->assertEquals('Anonymous', $cols[3]->getText());
      $operation_links = $cols[4]->findAll('css', 'a');
      $this->assertEquals('View in Collabora Online', $operation_links[0]->getText());
      $this->assertEquals(
        Url::fromRoute(
          'collabora-online.view',
          [
            'media' => $media->id(),
          ],
          [
            'query' => [
              'destination' => Url::fromRoute('view.group_media.page_1', [
                'group' => $group->id(),
              ])->toString(),
            ],
          ],
        )->toString(),
        $operation_links[0]->getAttribute('href'),
      );
      $this->assertEquals('Edit in Collabora Online', $operation_links[1]->getText());
      $this->assertEquals(
        Url::fromRoute(
          'collabora-online.edit',
          [
            'media' => $media->id(),
          ],
          [
            'query' => [
              'destination' => Url::fromRoute('view.group_media.page_1', [
                'group' => $group->id(),
              ])->toString(),
            ],
          ],
        )->toString(),
        $operation_links[1]->getAttribute('href'),
      );
      $i++;
    }
  }

}
