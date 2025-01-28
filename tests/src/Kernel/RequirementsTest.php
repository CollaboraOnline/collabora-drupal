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

/**
 * Tests the Collabora Online requirements.
 */
class RequirementsTest extends CollaboraKernelTestBase {

  /**
   * Test requirements.
   */
  public function testRequirements(): void {
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler */
    $moduleHandler = $this->container->get('module_handler');
    $moduleHandler->loadInclude('collabora_online', 'install');

    // Test missing JWT key and non-existing server.
    $requirements = \collabora_online_requirements('runtime');
    $this->assertNotEmpty($requirements);

    $this->assertEquals(
      'Collabora Online JWT key',
      (string) $requirements['collabora_online_settings_cool_key_id']['title'],
    );
    $this->assertEquals(
      'The Collabora Online configuration "JWT private key" is not set.',
      (string) $requirements['collabora_online_settings_cool_key_id']['description'],
    );
    $this->assertEquals(
      REQUIREMENT_ERROR,
      $requirements['collabora_online_settings_cool_key_id']['severity'],
    );

    $this->assertEquals(
      'Collabora Online server',
      (string) $requirements['collabora_online_settings_cool_server']['title'],
    );
    $this->assertEquals(
      'The Collabora Online server discovery.xml could not be accessed. Check the logs for more information.',
      (string) $requirements['collabora_online_settings_cool_server']['description'],
    );
    $this->assertEquals(
      REQUIREMENT_ERROR,
      $requirements['collabora_online_settings_cool_server']['severity'],
    );

    // Test that after meeting the requirements the errors are gone.
    $this->config('collabora_online.settings')
      ->set('cool.server', 'http://collabora.test:9980/')
      ->save();
    $this->config('collabora_online.settings')
      ->set('cool.key_id', 'collabora')
      ->save();

    $requirements = \collabora_online_requirements('runtime');
    $this->assertEmpty($requirements);
  }

}
