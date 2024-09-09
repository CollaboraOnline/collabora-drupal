<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Functional;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\PermissionHandlerInterface;

/**
 * Tests dynamically created permissions.
 */
class PermissionTest extends BrowserTestBase {

    use MediaTypeCreationTrait;

    /**
     * {@inheritdoc}
     */
    protected static $modules = [
        'media',
        'collabora_online',
    ];

    /**
     * {@inheritdoc}
     */
    protected $defaultTheme = 'stark';

    /**
     * Tests that dynamic permissions are properly created.
     */
    public function testDynamicPermissions(): void {
        $this->createMediaType('file', [
            'id' => 'public_wiki',
            'label' => 'Public wiki',
        ]);
        /** @var \Drupal\user\PermissionHandlerInterface $permission_handler */
        $permission_handler = \Drupal::service(PermissionHandlerInterface::class);
        $permissions = $permission_handler->getPermissions();
        $permissions = array_filter(
            $permissions,
            fn (array $permission) => $permission['provider'] === 'collabora_online',
        );
        // Remove noise that is hard to diff.
        $permissions = array_map(
            static function (array $permission) {
                $permission['title'] = (string) $permission['title'];
                if ($permission['description'] === NULL) {
                    unset($permission['description']);
                }
                if ($permission['provider'] === 'collabora_online') {
                    unset($permission['provider']);
                }
                return $permission;
            },
            $permissions,
        );
        ksort($permissions);
        $this->assertSame([
            'administer collabora instance' => [
                'title' => 'Administer the Collabora instance',
                'restrict access' => TRUE,
            ],
            'edit any public_wiki in collabora' => [
                'title' => '<em class="placeholder">Public wiki</em>: Edit any media file in Collabora',
                'dependencies' => ['config' => ['media.type.public_wiki']],
            ],
            'edit own public_wiki in collabora' => [
                'title' => '<em class="placeholder">Public wiki</em>: Edit own media file in Collabora',
                'dependencies' => ['config' => ['media.type.public_wiki']],
            ],
            'preview public_wiki in collabora' => [
                'title' => '<em class="placeholder">Public wiki</em>: Preview media file in Collabora',
                'dependencies' => ['config' => ['media.type.public_wiki']],
            ],
        ], $permissions);
    }

}
