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

use Drupal\collabora_online\Discovery\CollaboraDiscoveryFetcherInterface;
use Drupal\key\Entity\Key;

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
    $requirements = collabora_online_requirements('runtime');
    $this->assertCount(2, $requirements);

    $this->assertEquals(
      'Collabora Online JWT key',
      (string) $requirements['collabora_online_settings_cool_key_id']['title'],
    );
    $this->assertEquals(
      'The Collabora Online configuration "JWT private key" is not set or does not exist.',
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

    // Set a value for the key.
    Key::create([
      'id' => 'collabora_test',
    ])->save();
    $this->config('collabora_online.settings')
      ->set('cool.key_id', 'collabora_test')
      ->save();

    $requirements = collabora_online_requirements('runtime');
    $this->assertCount(1, $requirements);
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

    // Mock fetcher to get a discovery XML.
    $fetcher = $this->createMock(CollaboraDiscoveryFetcherInterface::class);
    $file = dirname(__DIR__, 2) . '/fixtures/discovery.mimetypes.xml';
    $fetcher->method('getDiscoveryXml')->willReturnCallback(function () use (&$file) {
      return file_get_contents($file);
    });
    $this->container->set(CollaboraDiscoveryFetcherInterface::class, $fetcher);

    $requirements = collabora_online_requirements('runtime');
    $this->assertCount(1, $requirements);
    $this->assertEquals(
      'Collabora Online WOPI proof',
      (string) $requirements['collabora_online_settings_wopi_proof']['title'],
    );
    $this->assertEquals(
      'Validation of the WOPI proof header is enabled, but no valid proof keys have been found in the configured Collabora Online server.',
      (string) $requirements['collabora_online_settings_wopi_proof']['description'],
    );
    $this->assertEquals(
      REQUIREMENT_ERROR,
      $requirements['collabora_online_settings_wopi_proof']['severity'],
    );

    // Disable the proof validation.
    $this->config('collabora_online.settings')
      ->set('cool.wopi_proof', FALSE)
      ->save();
    $requirements = collabora_online_requirements('runtime');
    $this->assertSame([], $requirements);

    // Re-enable the proof validation.
    $this->config('collabora_online.settings')
      ->set('cool.wopi_proof', TRUE)
      ->save();
    // Do a non-full check, just to make sure that the validation is being
    // triggered.
    $requirements = collabora_online_requirements('runtime');
    $this->assertCount(1, $requirements);
    $this->assertArrayHasKey('collabora_online_settings_wopi_proof', $requirements);

    // Change the XML response to contain a proof key.
    $file = dirname(__DIR__, 2) . '/fixtures/discovery.proof-key.xml';
    $requirements = collabora_online_requirements('runtime');
    $this->assertSame([], $requirements);
  }

}
