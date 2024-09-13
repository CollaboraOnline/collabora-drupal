<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests access to collabora routes.
 */
class AccessTest extends BrowserTestBase {

    use MediaTypeCreationTrait;
    use TestFileCreationTrait;

    /**
     * {@inheritdoc}
     */
    protected static $modules = [
        'node',
        'media',
        'collabora_online',
    ];

    /**
     * {@inheritdoc}
     */
    protected $defaultTheme = 'stark';

    /**
     * Tests the custom assertion method with core paths.
     */
    public function testAssertPathsAccessByUsers(): void {
        $this->createMediaType('file', ['id' => 'document']);
        $media = $this->createMediaEntity('document');
        $media_id = $media->id();

        $users = [
            'anonymous' => new AnonymousUserSession(),
            'authenticated' => $this->createUser(),
            'admin' => $this->createUser(admin: TRUE),
        ];

        // Build a report and assert the full result at once.
        // This provides a very complete picture in case the assertion fails.
        $this->assertPathsAccessByUsers(
            [
                // Test the front page as an example that everybody can access.
                '/' => ['anonymous', 'authenticated', 'admin'],
                // Test an administration page that only admin can see.
                '/admin/config' => ['admin'],
                // Test the user route.
                '/user/' . $users['authenticated']->id() => ['authenticated', 'admin'],
                // Test the core media route for reference.
                "/media/$media_id/edit" => ['admin'],
            ],
            $users,
        );
    }

    /**
     * Tests a scenario when only the administrator has access.
     */
    public function testOnlyAdminHasAccess(): void {
        $this->createMediaType('file', ['id' => 'document']);
        $media = $this->createMediaEntity('document');

        $users = [
            'anonymous' => new AnonymousUserSession(),
            'authenticated' => $this->createUser(),
            'admin' => $this->createUser(admin: TRUE),
        ];

        // Both routes are only accessible for admin.
        $this->assertPathsAccessByUsers(
            [
                '/cool/view/' . $media->id() => ['admin'],
                '/cool/edit/' . $media->id() => ['admin'],
            ],
            $users,
        );
    }

    /**
     * Tests a scenario where specific permissions are given to users.
     */
    public function testCollaboraMediaPermissions(): void {
        $this->createMediaType('file', ['id' => 'document']);
        $this->createMediaType('file', ['id' => 'public_wiki']);
        $this->createMediaType('file', ['id' => 'public_announcement']);
        $this->createMediaType('file', ['id' => 'diary']);
        $this->grantPermissions(
            Role::load(RoleInterface::ANONYMOUS_ID),
            [
                'preview public_announcement in collabora',
                'preview public_wiki in collabora',
                'edit any public_wiki in collabora',
            ],
        );

        $accounts = [
            'anonymous' => new AnonymousUserSession(),
            'authenticated' => $this->createUser(),
            'reader' => $this->createUser([
                'preview document in collabora',
            ]),
            'editor' => $this->createUser([
                'preview document in collabora',
                'edit any document in collabora',
            ]),
            // The 'writer' has write access, but no read access.
            'writer' => $this->createUser([
                'edit any document in collabora',
            ]),
            'diary keeper' => $this->createUser([
                // There is no 'preview own *' permission in this module.
                'preview diary in collabora',
                'edit own diary in collabora'
            ]),
        ];

        $media_entities = [
            'document' => $this->createMediaEntity('document'),
            'wiki' => $this->createMediaEntity('public_wiki'),
            'announcement' => $this->createMediaEntity('public_announcement'),
            'own diary' => $this->createMediaEntity('diary', [
                'uid' => $accounts['diary keeper']->id(),
            ]),
            'other diary' => $this->createMediaEntity('diary'),
        ];

        $paths = [];
        foreach ($media_entities as $media_key => $media) {
            $paths["/cool/view/<$media_key>"] = '/cool/view/' . $media->id();
            $paths["/cool/edit/<$media_key>"] = '/cool/edit/' . $media->id();
        }

        $this->assertPathsAccessByUsers(
            [
                '/cool/view/<document>' => ['reader', 'editor'],
                '/cool/edit/<document>' => ['editor', 'writer'],
                '/cool/view/<wiki>' => ['anonymous'],
                '/cool/edit/<wiki>' => ['anonymous'],
                '/cool/view/<announcement>' => ['anonymous'],
                '/cool/edit/<announcement>' => [],
                '/cool/view/<own diary>' => ['diary keeper'],
                '/cool/edit/<own diary>' => ['diary keeper'],
                '/cool/view/<other diary>' => ['diary keeper'],
                '/cool/edit/<other diary>' => [],
            ],
            $accounts,
            $paths,
        );
    }

    /**
     * Builds a report about which users can access a given content.
     *
     * @param array<string, list<string>> $expected
     *   Array indicating which url should be accessible by which user.
     *   The array keys are either paths or string keys from the $paths array.
     *   The array values are lists of keys from the $accounts array with access
     *   to that path.
     * @param array<string, \Drupal\Core\Session\AccountInterface> $accounts
     *   Accounts to test access with, keyed by a distinguishable name.
     * @param array<string, string>|null $paths
     *   An array of paths, or NULL to just use the array keys from $expected.
     *   This parameter is useful if the paths all look very similar.
     */
    protected function assertPathsAccessByUsers(array $expected, array $accounts, array $paths = NULL): void {
        if ($paths === NULL) {
            $paths = array_keys($expected);
            $paths = array_combine($paths, $paths);
        }
        // Build a report and assert it all at once, to have a more complete
        // overview on failure.
        $actual = [];
        foreach ($paths as $path_key => $path) {
            $url = Url::fromUserInput($path);
            // Filter the user list by access to the url.
            $accounts_with_access = array_filter($accounts, $url->access(...));
            $actual[$path_key] = array_keys($accounts_with_access);
        }
        // Use yaml to avoid integer keys in list output.
        $this->assertSame(
            Yaml::encode($expected),
            Yaml::encode($actual),
            'Users with access to given paths'
        );
    }

    /**
     * Creates a media entity with attached file.
     *
     * @param string $type
     *   Media type.
     * @param array $values
     *   Values for the media entity.
     *
     * @return \Drupal\media\MediaInterface
     *   New media entity.
     */
    protected function createMediaEntity(string $type, array $values = []): MediaInterface {
        file_put_contents('public://test.txt', 'Hello test');
        $file = File::create([
            'uri' => 'public://test.txt',
        ]);
        $file->save();
        $values += [
            'bundle' => $type,
            'field_media_file' => $file->id(),
        ];
        $media = Media::create($values);
        $media->save();
        return $media;
    }

}
