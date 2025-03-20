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

namespace Drupal\Tests\collabora_online\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\collabora_online\Traits\MediaCreationTrait;

/**
 * @covers \collabora_online_entity_operation()
 */
class EntityOperationsTest extends CollaboraKernelTestBase {

  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online_test',
    'views',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'views',
      'collabora_online_test',
    ]);
  }

  /**
   * Tests operations depending on media type.
   */
  public function testUnsupportedEntities(): void {
    $account = $this->createUser([
      'preview document in collabora',
      'edit any document in collabora',
    ]);
    // Create a user who can see editor links for all published media.
    $this->setCurrentUser($account);

    // Create a regular media entity.
    $media = $this->createMediaEntity('document');
    $this->assertOperationsForEntity(['preview', 'edit'], $media);

    // Create a media entity without a file.
    $media = $this->createMediaEntity('document', [
      'field_media_file' => NULL,
    ]);
    $this->assertOperationsForEntity([], $media);

    // Create a non-media entity.
    $entity = EntityTest::create([]);
    $this->assertOperationsForEntity([], $entity);
  }

  /**
   * Tests operations depending on user permissions, media status and ownership.
   */
  public function testOperationUserAccess(): void {
    // User without permissions can't see links.
    $this->assertOperationsForUser(
      [],
      $this->createUser([]),
    );
    // User with 'Preview' permission can see preview links on published media.
    $this->assertOperationsForUser(
      [
        'other published' => ['preview'],
        'own published' => ['preview'],
      ],
      $this->createUser([
        'preview document in collabora',
      ]),
    );
    // User with 'Preview own unpublished' permission can see a preview link for
    // their own unpublished media.
    $this->assertOperationsForUser(
      [
        'own unpublished' => ['preview'],
      ],
      $this->createUser([
        'preview own unpublished document in collabora',
      ]),
    );
    // User with different 'edit' permissions but no 'preview' permission cannot
    // see either.
    $this->assertOperationsForUser(
      [],
      $this->createUser([
        'edit any document in collabora',
        'edit own document in collabora',
      ]),
    );
  }

  /**
   * Asserts operations for a given user.
   *
   * Operations are tested on 4 media entities:
   *   - One published media owned by another user.
   *   - One unpublished media owned by another user.
   *   - One published media owned by $account.
   *   - One unpublished media owned by $account.
   *
   * @param array<string, list<'preview'|'edit'>> $expected_operations_by_media
   *   Expected operations for each media in the list.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to be used to run the test.
   */
  protected function assertOperationsForUser(array $expected_operations_by_media, AccountInterface $account): void {
    $this->setCurrentUser($account);
    // Create medias: cover all combinations of status and ownership.
    $medias = [
      'other published' => $this->createMediaEntity('document', [
        'uid' => $this->createUser(),
      ]),
      'other unpublished' => $this->createMediaEntity('document', [
        'uid' => $this->createUser(),
        'status' => 0,
      ]),
      'own published' => $this->createMediaEntity('document', [
        'uid' => $account->id(),
      ]),
      'own unpublished' => $this->createMediaEntity('document', [
        'uid' => $account->id(),
        'status' => 0,
      ]),
    ];
    $actual_operations_by_media = [];
    foreach ($medias as $media_key => $media) {
      $actual_operations_by_media[$media_key] = $this->assertAndGetOperations($media, $media_key);
    }
    $this->assertSame($expected_operations_by_media, array_filter($actual_operations_by_media));
  }

  /**
   * Asserts operations for a given entity.
   *
   * @param list<'preview'|'edit'> $expected_operations
   *   Expected operation short names.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to build operations for.
   */
  protected function assertOperationsForEntity(array $expected_operations, EntityInterface $entity): void {
    $this->assertSame($expected_operations, $this->assertAndGetOperations($entity, ''));
  }

  /**
   * Gets operation shortnames for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to build the operations.
   * @param string $message
   *   A message to pass to assertions.
   *
   * @return list<'preview'|'edit'>
   *   Shortnames for the operations found.
   */
  protected function assertAndGetOperations(EntityInterface $entity, string $message): array {
    $list_builder = \Drupal::entityTypeManager()->getListBuilder($entity->getEntityTypeId());
    $operations = $list_builder->getOperations($entity);
    $operation_shortnames = [];
    foreach ($operations as $operation_key => $operation) {
      $operation_shortname = match ($operation_key) {
        'collabora_online_view' => 'preview',
        'collabora_online_edit' => 'edit',
        default => NULL,
      };
      if ($operation_shortname === NULL) {
        continue;
      }
      $operation_shortnames[] = $operation_shortname;
      $this->assertSame(50, $operation['weight'], "$message - $operation_key");
      $url = $operation['url'];
      $this->assertInstanceOf(Url::class, $url);
      if ($operation_shortname === 'preview') {
        $this->assertSame('View in Collabora Online', (string) $operation['title'], $message);
        $this->assertSame('/cool/view/' . $entity->id(), $url->toString(), $message);
      }
      else {
        $this->assertSame('Edit in Collabora Online', (string) $operation['title'], $message);
        $this->assertSame('/cool/edit/' . $entity->id(), $url->toString(), $message);
      }
    }
    return $operation_shortnames;
  }

}
