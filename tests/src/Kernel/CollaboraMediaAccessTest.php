<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\collabora_online\Traits\MediaCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\RoleInterface;

/**
 * Tests access for media operations and routes.
 */
class CollaboraMediaAccessTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;
  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online',
    'user',
    'media',
    'field',
    'image',
    'system',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');
    $this->installConfig([
      'field',
      'system',
      'user',
      'image',
      'file',
      'media',
    ]);

    $this->createMediaType('file', ['id' => 'document']);
    $this->createMediaType('file', ['id' => 'book']);

    // Consume the user id 1.
    $this->createUser();
  }

  /**
   * Tests media access for Collabora routes and operations.
   */
  public function testCollaboraMediaAccess(): void {
    $this->assertCollaboraMediaAccess(
      [],
      new AnonymousUserSession(),
      'No access for anonymous without permissions',
    );

    // Check authenticated with irrelevant permissions.
    // This also covers the "no permissions" case.
    $this->assertCollaboraMediaAccess(
      [],
      $this->createUser([
        // Add general 'media' permissions.
        'administer media types',
        'view media',
        'update any media',
        'view own unpublished media',
        // Add Collabora permissions for a different media type.
        'preview book in collabora',
        'preview own unpublished book in collabora',
        'edit any book in collabora',
        'edit own book in collabora',
      ]),
      'No access with irrelevant permissions',
    );

    $this->assertCollaboraMediaAccessForPermission(
      [
        'published document' => ['preview'],
        'own published document' => ['preview'],
      ],
      'preview document in collabora',
    );

    $this->assertCollaboraMediaAccessForPermission(
      [
        'own unpublished document' => ['preview'],
      ],
      'preview own unpublished document in collabora',
    );

    $this->assertCollaboraMediaAccessForPermission(
      [
        'published document' => ['edit'],
        'unpublished document' => ['edit'],
        'own published document' => ['edit'],
        'own unpublished document' => ['edit'],
      ],
      'edit any document in collabora',
    );

    $this->assertCollaboraMediaAccessForPermission(
      [
        'own published document' => ['edit'],
        'own unpublished document' => ['edit'],
      ],
      'edit own document in collabora',
    );

    // The 'administer media' permission grants access to everything.
    $this->assertCollaboraMediaAccessForPermission(
      [
        'published document' => ['preview', 'edit'],
        'unpublished document' => ['preview', 'edit'],
        'own published document' => ['preview', 'edit'],
        'own unpublished document' => ['preview', 'edit'],
      ],
      'administer media',
    );

    $this->assertCollaboraMediaAccess(
      [
        'published document' => ['preview', 'edit'],
        'unpublished document' => ['preview', 'edit'],
        'own published document' => ['preview', 'edit'],
        'own unpublished document' => ['preview', 'edit'],
      ],
      $this->createUser(admin: TRUE),
      "Access for admin user",
    );
  }

  /**
   * Tests scenarios where the anonymous user has more permissions.
   *
   * This verifies the special treatment of uid 0 to determine the owner of a
   * media entity.
   */
  public function testAnonymousOwnAccess(): void {
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'preview own unpublished document in collabora',
      'edit own document in collabora',
    ]);
    $this->assertCollaboraMediaAccess(
      [],
      new AnonymousUserSession(),
      "Anonymous user with '... own ...' permissions.",
    );

    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'preview document in collabora',
      'edit any document in collabora',
    ]);
    $this->assertCollaboraMediaAccess(
      [
        'published document' => ['preview', 'edit'],
        'unpublished document' => ['edit'],
        'own published document' => ['preview', 'edit'],
        'own unpublished document' => ['edit'],
      ],
      new AnonymousUserSession(),
      "Anonymous user with all Collabora media permissions.",
    );
  }

  /**
   * Creates a user with one permission, and asserts access to media entities.
   *
   * @param array<string, list<'preview', 'edit'>> $expected
   *   Expected access.
   * @param string $permission
   *   Permission machine name.
   */
  protected function assertCollaboraMediaAccessForPermission(array $expected, string $permission): void {
    $account = $this->createUser([$permission]);
    $message = "User with '$permission' permission.";
    $this->assertCollaboraMediaAccess($expected, $account, $message);
  }

  /**
   * Asserts Collabora media access for a user account.
   *
   * @param array<string, list<'preview', 'edit'> $expected
   *   Expected access matrix.
   *   The keys identify media entities that are created in this test.
   *   The values identify operations.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account to check access for.
   * @param string $message
   *   Message for the assertion.
   */
  protected function assertCollaboraMediaAccess(array $expected, AccountInterface $account, string $message): void {
    $other_user = $this->createUser();
    $entities = [
      "published document" => $this->createMediaEntity('document', [
        'uid' => $other_user->id(),
      ]),
      "unpublished document" => $this->createMediaEntity('document', [
        'uid' => $other_user->id(),
        'status' => 0,
      ]),
      "own published document" => $this->createMediaEntity('document', [
        'uid' => $account->id(),
      ]),
      "own unpublished document" => $this->createMediaEntity('document', [
        'uid' => $account->id(),
        'status' => 0,
      ]),
    ];

    // Test $entity->access() with different operations on all entities.
    $operations = [
      'preview' => 'preview in collabora',
      'edit' => 'edit in collabora',
    ];
    $actual_entity_access = [];
    foreach ($entities as $entity_key => $entity) {
      foreach ($operations as $operation_key => $operation) {
        $has_entity_access = $entity->access($operation, $account);
        if ($has_entity_access) {
          $actual_entity_access[$entity_key][] = $operation_key;
        }
      }
    }
    $this->assertSameYaml(
      $expected,
      $actual_entity_access,
      'Entity access: ' . $message,
    );

    // Test path access.
    // The result is expected to be exactly the same, due to how the route
    // access is configured.
    // Testing the paths like this introduces some level of redundancy or
    // duplication, but it is cheap and easy, so for now this is what we do.
    $sprintf_path_patterns = [
      'preview' => '/cool/view/%s',
      'edit' => '/cool/edit/%s',
    ];
    $actual_path_access = [];
    foreach ($entities as $entity_key => $entity) {
      foreach ($sprintf_path_patterns as $pattern_key => $sprintf_path_pattern) {
        $path = sprintf($sprintf_path_pattern, $entity->id());
        $has_path_access = Url::fromUserInput($path)->access($account);
        if ($has_path_access) {
          $actual_path_access[$entity_key][] = $pattern_key;
        }
      }
    }
    $this->assertSameYaml(
      $expected,
      $actual_path_access,
      'Path access: ' . $message,
    );
  }

  /**
   * Asserts that two values are the same when exported to yaml.
   *
   * This provides a nicer diff output, without numeric array keys.
   *
   * @param mixed $expected
   *   Expected value.
   * @param mixed $actual
   *   Actual value.
   * @param string $message
   *   Message.
   */
  protected function assertSameYaml(mixed $expected, mixed $actual, string $message = ''): void {
    $this->assertSame(
      "\n" . Yaml::encode($expected),
      "\n" . Yaml::encode($actual),
      $message,
    );
  }

}
