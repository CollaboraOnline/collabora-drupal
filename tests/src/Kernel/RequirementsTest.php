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

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\key\Entity\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the Collabora Online requirements.
 */
class RequirementsTest extends CollaboraKernelTestBase {

  /**
   * Test requirements.
   */
  public function testRequirements(): void {
    // Mock the http client to get a discovery XML.
    $this->createMockHttpClient($file_reference);

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

    // No need to invalidate the discovery cache at this point, because a failed
    // discovery is not cached.
    $file_reference = dirname(__DIR__, 2) . '/fixtures/discovery.mimetypes.xml';

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
    $file_reference = dirname(__DIR__, 2) . '/fixtures/discovery.proof-key.xml';
    $this->invalidateDiscoveryCache();

    $requirements = collabora_online_requirements('runtime');
    $this->assertSame([], $requirements);
  }

  /**
   * Creates and registers a mock http client.
   *
   * @param string|null $file
   *   Path to a mock XML file, by reference.
   *   Can be changed to let the http client return different xml content.
   */
  protected function createMockHttpClient(string|null &$file = NULL): void {
    $original_client = $this->container->get('http_client');
    $http_client_get = function (...$args) use (&$file, $original_client) {
      if ($file === NULL) {
        return $original_client->get(...$args);
      }
      $xml = file_get_contents($file);
      return new Response(
        200,
        [],
        $xml,
      );
    };
    $client = $this->createMock(Client::class);
    $client->method('get')
      ->willReturnCallback(
        function (...$args) use (&$http_client_get): Response {
          return $http_client_get(...$args);
        },
      );
    $this->container->set('http_client', $client);
  }

  /**
   * Invalidates the discovery cache.
   */
  protected function invalidateDiscoveryCache(): void {
    /** @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface $invalidator */
    $invalidator = \Drupal::service(CacheTagsInvalidatorInterface::class);
    $invalidator->invalidateTags(['config:collabora_online.settings']);
  }

}
