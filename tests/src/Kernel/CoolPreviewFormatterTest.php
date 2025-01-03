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
    $media_display = $this->createCoolPreviewField($field_name, 'media', 'document');

    $media = Media::create([
      'bundle' => 'document',
      $field_name => '1',
    ]);
    $media->save();

    $user = $this->createUser([
      'access content',
      'preview document in collabora',
    ]);
    $this->setCurrentUser($user);

    // Field is rendered correctly.
    $this->assertCoolPreviewField(
      [
        $media->getName(),
      ],
      [
        'contexts' => ['user.permissions'],
        'tags' => ['media:1'],
        'max-age' => Cache::PERMANENT,
      ],
      $media_display->build($media)[$field_name]
    );

    // Iframe is not displayed for other entities than media.
    $test_display = $this->createCoolPreviewField($field_name, 'entity_test', 'entity_test');
    $entity = EntityTest::create([
      $field_name => '1',
    ]);
    $entity->save();
    $this->assertCoolPreviewField(
      [],
      [
        'contexts' => [],
        'tags' => [],
        'max-age' => Cache::PERMANENT,
      ],
      $test_display->build($entity)[$field_name]
    );

    // Iframe is not displayed for users without permission.
    $this->setCurrentUser($this->createUser(['access content']));
    $this->assertCoolPreviewField(
      [],
      [
        'contexts' => ['user.permissions'],
        'tags' => ['media:1'],
        'max-age' => Cache::PERMANENT,
      ],
      $media_display->build($media)[$field_name]
    );
    $this->setCurrentUser($user);

    // Iframe is not displayed for empty value field.
    $this->setCurrentUser($this->createUser([
      'access content',
      'preview document in collabora',
    ]));
    File::load('1')->delete();
    $this->assertCoolPreviewField(
      [],
      [
        'contexts' => ['user.permissions'],
        'tags' => ['media:1'],
        'max-age' => Cache::PERMANENT,
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
  protected function createCoolPreviewField(string $field_name, string $entity_type, string $bundle): EntityDisplayInterface {
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
   * Asserts 'collabora_preview' HTML output and render array cache and library.
   *
   * @param array $expected_medias
   *   List of media names.
   * @param array $expected_cache
   *   The expected cache.
   * @param array $build
   *   The render array.
   */
  protected function assertCoolPreviewField(array $expected_medias, array $expected_cache, array $build): void {
    // Check cache in render array.
    $this->assertEqualsCanonicalizing($expected_cache, $build['#cache']);

    $crawler = new Crawler((string) \Drupal::service('renderer')->renderRoot($build));

    // Library is present in case we have medias to render.
    $expected_libraries = $expected_medias ? ['library' => ['collabora_online/cool.previewer']] : [];
    $this->assertEquals($expected_libraries, $build['#attached']);

    $elements = $crawler->filter('div.cool-preview__wrapper');
    $this->assertSameSize($expected_medias, $elements);

    // Check each of the files from the media.
    foreach ($expected_medias as $i => $media) {
      $button_wrapper = $elements->eq($i)->filter('p');
      $this->assertCount(1, $button_wrapper);
      $this->assertEquals($media . ' View', $button_wrapper->text());
      // The button to preview.
      $button = $button_wrapper->filter('button');
      $this->assertCount(1, $button_wrapper);
      $this->assertEquals('View', $button->text());
      // The iframe is present.
      $dialog = $elements->eq($i)->filter('dialog#cool-editor__dialog.cool-editor__dialog');
      $this->assertCount(1, $dialog);
      $this->assertCount(1, $dialog->filter('iframe.cool-frame__preview'));
    }
  }

}
