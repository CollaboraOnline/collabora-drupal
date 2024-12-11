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

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\collabora_online\Traits\MediaCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class for kernel tests with collabora_online and media.
 *
 * Adds modules and traits that are used in most of these tests.
 */
abstract class CollaboraKernelTestBase extends KernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;
  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online',
    'key',
    'media',
    'user',
    'field',
    'system',
    'file',
    'image',
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

    // Install user module to avoid user 1 permissions bypass.
    \Drupal::moduleHandler()->loadInclude('user', 'install');
    user_install();
  }

}
