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

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @coversDefaultClass \Drupal\collabora_online\Plugin\Field\FieldFormatter\CoolPreview
 */
class CoolPreviewFormatterTest extends CollaboraKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->createMediaType('file', ['id' => 'document']);

    // Create test file.
    file_put_contents('public://file.txt', $this->randomString());
    File::create(['uri' => 'public://test.txt'])->save();
  }

  /**
   * Tests that field is rendered.
   *
   * @covers ::viewElements
   */
  public function testViewElements(): void {
    // Create field configuration and content.
    $field_name = $this->randomMachineName();
    $media_display = $this->prepareFieldAndDisplay($field_name, 'media', 'document');

    $media = Media::create([
      'bundle' => 'document',
      $field_name => 1,
    ]);
    $media->save();

    $user = $this->createUser([
      'access content',
      'preview document in collabora',
    ]);
    $this->setCurrentUser($user);

    // Field is rendered correctly.
    $build = $media_display->build($media)[$field_name];
    $this->assertSame(
      [
        'contexts' => ['user.permissions'],
        'tags' => ['media:1'],
        'max-age' => Cache::PERMANENT,
      ],
      $build['#cache'],
    );
    $this->assertCoolPreviewField($media->getName(), $build);

    // Iframe is not displayed for other entities than media.
    $test_display = $this->prepareFieldAndDisplay($field_name, 'entity_test', 'entity_test');
    $entity = EntityTest::create([
      $field_name => 1,
    ]);
    $entity->save();
    $this->assertSame(
      [
        '#cache' => [
          'contexts' => [],
          'tags' => [],
          'max-age' => Cache::PERMANENT,
        ],
      ],
      $test_display->build($entity)[$field_name]
    );

    // Iframe is not displayed for users without permission.
    $this->setCurrentUser($this->createUser(['access content']));
    $this->assertSame(
      [
        '#cache' => [
          'contexts' => ['user.permissions'],
          'tags' => ['media:1'],
          'max-age' => Cache::PERMANENT,
        ],
      ],
      $media_display->build($media)[$field_name]
    );
    $this->setCurrentUser($user);

    // Iframe is not displayed for empty value field.
    $this->setCurrentUser($this->createUser([
      'access content',
      'preview document in collabora',
    ]));
    File::load(1)->delete();
    $this->assertSame(
      [
        '#cache' => [
          'contexts' => ['user.permissions'],
          'tags' => ['media:1'],
          'max-age' => Cache::PERMANENT,
        ],
      ],
      $media_display->build($media)[$field_name]
    );
  }

  /**
   * Creates a file field using 'collabora_preview' formatter.
   *
   * @param string $field_name
   *   The field name.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The display where the field is set.
   */
  protected function prepareFieldAndDisplay(string $field_name, string $entity_type, string $bundle): EntityDisplayInterface {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'file',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
    ]);
    $instance->save();

    /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface $display */
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay($entity_type, $bundle)
      ->setComponent($field_name, [
        'type' => 'collabora_preview',
        'settings' => [],
      ]);
    $display->save();

    return $display;
  }

  /**
   * Asserts HTML output from the field formatter.
   *
   * @param string $expected_media_name
   *   The media entity label.
   * @param array $build
   *   The field render array.
   */
  protected function assertCoolPreviewField(string $expected_media_name, array $build): void {
    $crawler = new Crawler((string) \Drupal::service('renderer')->renderRoot($build));

    // Library is present after rendering the array.
    $this->assertEquals(['library' => ['collabora_online/cool.previewer']], $build['#attached']);

    $expected_html = <<<END
<div class="cool-preview__wrapper">
  <p>$expected_media_name <button onclick="previewField('/cool/view/1');">View</button></p>
  <dialog id="cool-editor__dialog" class="cool-editor__dialog">
    <iframe class="cool-frame__preview"></iframe>
  </dialog>
</div>
END;

    // Only one preview element is present.
    $elements = $crawler->filter('div.cool-preview__wrapper');
    $this->assertCount(1, $elements);
    $this->assertEquals($expected_html, $elements->outerHtml());
  }

}
