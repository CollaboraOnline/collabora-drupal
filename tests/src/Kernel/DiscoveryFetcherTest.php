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

use Drupal\collabora_online\Discovery\DiscoveryFetcher;
use Drupal\collabora_online\Discovery\DiscoveryFetcherInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\collabora_online\Traits\KernelTestLoggerTrait;
use Drupal\Tests\collabora_online\Traits\KernelTestTimeTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Drupal\collabora_online\Discovery\DiscoveryFetcher
 */
class DiscoveryFetcherTest extends KernelTestBase {

  use KernelTestLoggerTrait;
  use KernelTestTimeTrait;

  /**
   * XML content to be returned for a discovery request.
   *
   * @var string
   */
  protected string $xml;

  /**
   * Callback to replace Client->get().
   *
   * The default callback will return a response with $this->xml.
   * It can be replaced to e.g. throw an exception.
   *
   * @var \Closure
   */
  protected \Closure $httpClientGet;

  /**
   * Collects calls to Client->get().
   *
   * Each entry is a list of arguments.
   *
   * @var list<array>
   */
  protected array $httpClientGetCalls = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online',
    'key',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'collabora_online',
    ]);

    $this->setUpLogger();

    // Mock the http client to get a discovery XML.
    $file = dirname(__DIR__, 2) . '/fixtures/discovery.mimetypes.xml';
    $this->xml = file_get_contents($file);
    $this->httpClientGet = fn(...$args) => new Response(
      200,
      [],
      $this->xml,
    );

    $client = $this->createMock(Client::class);
    $client->method('get')
      ->willReturnCallback(
        function (...$args): Response {
          $this->httpClientGetCalls[] = $args;
          return ($this->httpClientGet)(...$args);
        },
      );
    $this->container->set('http_client', $client);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertPostConditions(): void {
    parent::assertPostConditions();
    $this->assertNoFurtherLogMessages();
  }

  /**
   * Tests a successful call to ->getDiscovery().
   */
  public function testGetDiscovery(): void {
    $fetcher = $this->getFetcher();
    $discovery = $fetcher->getDiscovery();
    $this->assertSame(
      'http://collabora.test:9980/browser/61cf2b4/cool.html?',
      $discovery->getWopiClientURL(),
    );
    $this->assertSame(
      [
        [
          'https://localhost:9980/hosting/discovery',
          ['verify' => TRUE],
        ],
      ],
      $this->httpClientGetCalls,
    );
  }

  /**
   * Tests that subsequent calls are cached.
   */
  public function testGetDiscoveryIsCached(): void {
    $fetcher = $this->getFetcher();
    $load_cache = fn () => \Drupal::cache()->get(DiscoveryFetcher::CID);
    // Initially the cache is cold.
    $this->assertFalse($load_cache());

    // Fetching the discovery causes a http request, and creates a cache record.
    $fetcher->getDiscovery();
    $this->assertCount(1, $this->httpClientGetCalls);

    $cache_record = $load_cache();
    $this->assertNotFalse($cache_record);
    $this->assertSame(['config:collabora_online.settings'], $cache_record->tags);

    // Repeatedly fetching the discovery does not cause further http requests.
    $fetcher->getDiscovery();
    $this->assertCount(1, $this->httpClientGetCalls);

    // After invalidating the cache tag, fetching the discovery causes a new
    // http request.
    /** @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface $invalidator */
    $invalidator = \Drupal::service(CacheTagsInvalidatorInterface::class);
    $invalidator->invalidateTags(['config:collabora_online.settings']);

    $fetcher->getDiscovery();
    $this->assertCount(2, $this->httpClientGetCalls);

    // Set an arbitrary TTL for the discovery cache.
    // This configuration change invalidates the cache record.
    $this->config('collabora_online.settings')
      ->set('cool.discovery_cache_ttl', 12345)
      ->save();

    $fetcher->getDiscovery();
    $this->assertCount(3, $this->httpClientGetCalls);
    $cache_record = $load_cache();
    $this->assertNotFalse($cache_record);

    // Advance the mock request time by less than the configured TTL.
    $this->mockRequestTime = $this->mockRequestTime->add(\DateInterval::createFromDateString('12330 seconds'));
    // The cache record is still valid.
    $this->assertNotFalse($load_cache());
    // Fetching the discovery does not cause a http request.
    $fetcher->getDiscovery();
    $this->assertCount(3, $this->httpClientGetCalls);

    // Advance the mock request time beyond the cache record's expiration time.
    $this->mockRequestTime = $this->mockRequestTime->add(\DateInterval::createFromDateString('30 seconds'));
    // The cache record is now expired, and $cache->get() returns FALSE.
    $this->assertFalse($load_cache());
    // Fetching the discovery does cause another http request.
    $fetcher->getDiscovery();
    $this->assertCount(4, $this->httpClientGetCalls);

    // Set TTL = 0 for no cache, always refresh.
    // Each further fetching of the discovery will cause another http request.
    $this->config('collabora_online.settings')
      ->set('cool.discovery_cache_ttl', 0)
      ->save();
    $fetcher->getDiscovery();
    $this->assertCount(5, $this->httpClientGetCalls);
    $fetcher->getDiscovery();
    $this->assertCount(6, $this->httpClientGetCalls);
  }

  /**
   * Tests error behavior when the 'cool.server' setting is empty.
   */
  public function testConfigServerAddressTrailingSlashes(): void {
    $cases = ['', '/', '//', '///'];
    foreach ($cases as $i => $slash_string) {
      $this->config('collabora_online.settings')
        ->set('cool.server', "https://collabora.$i.example.com$slash_string")
        ->save();
      $this->getFetcher()->getDiscovery();
    }
    $this->assertCount(4, $this->httpClientGetCalls);
    foreach ($this->httpClientGetCalls as $i => $call) {
      $this->assertSame(
        "https://collabora.$i.example.com/hosting/discovery",
        $call[0],
      );
    }
  }

  /**
   * Tests the effect of the 'cool.disable_cert_check' setting.
   *
   * @see \GuzzleHttp\RequestOptions::VERIFY
   */
  public function testConfigDisableCertCheck(): void {
    // Also test with NULL to verify the fallback value.
    $cases = [TRUE, FALSE, NULL];
    foreach ($cases as $disable_cert_check) {
      $this->config('collabora_online.settings')
        ->set('cool.disable_cert_check', $disable_cert_check)
        ->save();
      $this->getFetcher()->getDiscovery();
    }
    $this->assertCount(3, $this->httpClientGetCalls);
    $this->assertFalse($this->httpClientGetCalls[0][1]['verify']);
    $this->assertTrue($this->httpClientGetCalls[1][1]['verify']);
    $this->assertTrue($this->httpClientGetCalls[2][1]['verify']);
  }

  /**
   * Tests error behavior for blank xml content.
   */
  public function testBlankXml(): void {
    $this->xml = '';
    try {
      $this->getFetcher()->getDiscovery();
      $this->fail('Expected exception was not thrown.');
    }
    catch (CollaboraNotAvailableException $e) {
      $this->assertSame('The discovery.xml file seems to be empty.', $e->getMessage());
    }
    $cache_record = \Drupal::cache()->get(DiscoveryFetcher::CID);
    $this->assertFalse($cache_record);
  }

  /**
   * Tests error behavior for malformed xml content.
   */
  public function testBrokenXml(): void {
    $this->xml = 'This file does not contain valid xml.';
    try {
      $this->getFetcher()->getDiscovery();
      $this->fail('Expected exception was not thrown.');
    }
    catch (CollaboraNotAvailableException $e) {
      $this->assertMatchesRegularExpression('#^Error in the retrieved discovery.xml file: #', $e->getMessage());
    }
    $cache_record = \Drupal::cache()->get(DiscoveryFetcher::CID);
    $this->assertFalse($cache_record);
  }

  /**
   * Tests behavior if the cache contains bad XML.
   */
  public function testBadValueInCache(): void {
    \Drupal::cache()->set(DiscoveryFetcher::CID, 'bad xml in cache');
    try {
      $this->getFetcher()->getDiscovery();
      $this->fail('Expected exception was not thrown.');
    }
    catch (CollaboraNotAvailableException $e) {
      $this->assertMatchesRegularExpression('#^Error in the retrieved discovery.xml file: #', $e->getMessage());
    }
    // The cache record is unchanged.
    $cache_record = \Drupal::cache()->get(DiscoveryFetcher::CID);
    $this->assertIsObject($cache_record);
    $this->assertSame('bad xml in cache', $cache_record->data);
  }

  /**
   * Tests error behavior for a failed request.
   */
  public function testClientException(): void {
    $this->httpClientGet = fn () => throw new TransferException('Request failed.');
    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessage('Not able to retrieve the discovery.xml file from the Collabora Online server.');
    try {
      $this->getFetcher()->getDiscovery();
    }
    finally {
      $this->assertLogMessage(
        RfcLogLevel::ERROR,
        "Failed to fetch from '@url': @message.",
        [
          '@url' => 'https://localhost:9980/hosting/discovery',
          '@message' => 'Request failed.',
        ],
      );
    }
  }

  /**
   * Tests error behavior when the 'cool.server' setting is empty.
   */
  public function testConfigServerAddressEmpty(): void {
    $this->config('collabora_online.settings')
      ->set('cool.server', '')
      ->save();
    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessage('The configured Collabora Online server address is empty.');
    $this->getFetcher()->getDiscovery();
  }

  /**
   * Tests error behavior when the 'cool.server' setting has a bad value.
   */
  public function testConfigServerAddressBadValue(): void {
    $this->config('collabora_online.settings')
      ->set('cool.server', '/bad/url')
      ->save();
    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessage("The configured Collabora Online server address must begin with 'http://' or 'https://'. Found '/bad/url'.");
    $this->getFetcher()->getDiscovery();
  }

  /**
   * Gets the discovery fetcher from the container.
   *
   * @return \Drupal\collabora_online\Discovery\DiscoveryFetcherInterface
   *   The discovery fetcher service.
   */
  protected function getFetcher(): DiscoveryFetcherInterface {
    $fetcher = \Drupal::service(DiscoveryFetcherInterface::class);
    $this->assertInstanceOf(DiscoveryFetcherInterface::class, $fetcher);
    return $fetcher;
  }

}
