<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Unit;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\collabora_online\Discovery\CollaboraDiscoveryFetcher;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @coversDefaultClass \Drupal\collabora_online\Discovery\CollaboraDiscoveryFetcher
 */
class DiscoveryLoaderTest extends UnitTestCase {

  /**
   * Tests successful behavior.
   */
  public function testGetDiscovery(): void {
    $file = dirname(__DIR__, 2) . '/fixtures/discovery.mimetypes.xml';
    $xml = file_get_contents($file);
    $logger = new TestLogger();
    $fetcher = $this->getFetcherFromXml($xml, $logger);
    $discovery = $fetcher->getDiscovery();
    $this->assertSame(
      'http://collabora.test:9980/browser/61cf2b4/cool.html?',
      $discovery->getWopiClientURL(),
    );
    $this->assertEmpty($logger->records);
  }

  /**
   * Tests error behavior for blank xml content.
   */
  public function testBlankXml(): void {
    $logger = new TestLogger();
    $fetcher = $this->getFetcherFromXml('', $logger);
    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessage('The discovery.xml file is empty.');
    try {
      $fetcher->getDiscovery();
    }
    finally {
      $this->assertEmpty($logger->records);
    }
  }

  /**
   * Tests error behavior for malformed xml content.
   */
  public function testBrokenXml(): void {
    $xml = 'This file does not contain valid xml.';
    $logger = new TestLogger();
    $fetcher = $this->getFetcherFromXml($xml, $logger);
    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessageMatches('#^Error in the retrieved discovery.xml file: #');
    try {
      $fetcher->getDiscovery();
    }
    finally {
      $this->assertEmpty($logger->records);
    }
  }

  /**
   * Gets a discovery instance based on test xml.
   *
   * @param string $xml
   *   Explicit XML content returned from the HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger.
   * @param array $cool_settings
   *   Settings in 'collabora_online.settings'/'cool'.
   * @param string $expected_url
   *   The url expected by the HTTP client.
   * @param array $expected_options
   *   Options expected by the HTTP client.
   *
   * @return \Drupal\collabora_online\Discovery\CollaboraDiscoveryFetcher
   *   New discovery loader based on the parameters.
   */
  protected function getFetcherFromXml(
    string $xml,
    LoggerInterface $logger,
    array $cool_settings = [],
    string $expected_url = 'http://collabora.example.com/hosting/discovery',
    array $expected_options = [
      RequestOptions::VERIFY => TRUE,
    ],
  ): CollaboraDiscoveryFetcher {
    $cool_settings += [
      'server' => 'http://collabora.example.com/',
      'disable_cert_check' => FALSE,
    ];
    return new CollaboraDiscoveryFetcher(
      $logger,
      $this->createConfigFactory([
        'collabora_online.settings' => [
          'cool' => $cool_settings,
        ],
      ]),
      $this->createMockClientFromXml(
        $expected_url,
        $expected_options,
        $xml,
      ),
      $this->createMock(CacheBackendInterface::class),
      'persistent_cid',
      new Time(),
    );
  }

  /**
   * Creates a mock HTTP client that will return specific xml.
   *
   * @param string $url
   *   Expected url for the $client->get() call.
   * @param array $options
   *   Expected options for the $client->get() call.
   * @param string $xml
   *   Explicit XML content expected in the response.
   *
   * @return \GuzzleHttp\Client
   *   A mock HTTP client.
   */
  protected function createMockClientFromXml(string $url, array $options, string $xml): Client {
    $response = new Response(200, [], $xml);
    $client = $this->createMock(Client::class);
    $client->method('get')
      ->with($url, $options)
      ->willReturn($response);

    return $client;
  }

  /**
   * Creates a config factory with mock data.
   *
   * We cannot use $this->getConfigFactoryStub() because the returned mock
   * config objects do not respond correctly to cache metadata method calls.
   *
   * @param array $settings
   *   Settings.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   New config factory.
   */
  protected function createConfigFactory(array $settings): ConfigFactoryInterface {
    $storage = new MemoryStorage();
    foreach ($settings as $name => $data) {
      $storage->write($name, $data);
    }
    return new ConfigFactory(
      $storage,
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(TypedConfigManagerInterface::class),
    );
  }

}
