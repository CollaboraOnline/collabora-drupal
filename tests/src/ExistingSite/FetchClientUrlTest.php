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

namespace Drupal\Tests\collabora_online\ExistingSite;

use Drupal\collabora_online\Discovery\DiscoveryFetcherInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Tests\collabora_online\Traits\ConfigurationBackupTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests requests to Collabora from PHP.
 */
class FetchClientUrlTest extends ExistingSiteBase {

  use ConfigurationBackupTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->backupSimpleConfig('collabora_online.settings');
  }

  /**
   * Tests fetching the client url.
   */
  public function testFetchClientUrl(): void {
    /** @var \Drupal\collabora_online\Discovery\DiscoveryFetcherInterface $discovery_fetcher */
    $discovery_fetcher = \Drupal::service(DiscoveryFetcherInterface::class);
    $discovery = $discovery_fetcher->getDiscovery();
    $client_url = $discovery->getWopiClientURL('text/plain', 'edit');
    $this->assertNotNull($client_url);
    // The protocol, domain and port are known when this test runs in the
    // docker-compose setup.
    $this->assertMatchesRegularExpression('@^http://collabora\.test:9980/browser/[0-9a-f]+/cool\.html\?$@', $client_url);
  }

  /**
   * Tests fetching client url when the connection is misconfigured.
   */
  public function testFetchClientUrlWithMisconfiguration(): void {
    \Drupal::configFactory()
      ->getEditable('collabora_online.settings')
      ->set('cool.server', 'httx://example.com')
      ->save();
    /** @var \Drupal\collabora_online\Discovery\DiscoveryFetcherInterface $discovery_fetcher */
    $discovery_fetcher = \Drupal::service(DiscoveryFetcherInterface::class);

    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessage("The configured Collabora Online server address must begin with 'http://' or 'https://'. Found 'httx://example.com'.");

    $discovery_fetcher->getDiscovery();
  }

}
