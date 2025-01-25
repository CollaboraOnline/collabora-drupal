<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Unit;

use Drupal\collabora_online\Discovery\CollaboraDiscoveryFetcherInterface;
use Drupal\collabora_online\Discovery\DiscoveryLoader;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\collabora_online\Discovery\DiscoveryLoader
 */
class DiscoveryLoaderTest extends UnitTestCase {

  /**
   * Tests successful behavior.
   */
  public function testGetDiscovery(): void {
    $file = dirname(__DIR__, 2) . '/fixtures/discovery.mimetypes.xml';
    $xml = file_get_contents($file);
    $loader = $this->getLoaderFromXml($xml);
    $discovery = $loader->getDiscovery();
    $this->assertSame(
      'http://collabora.test:9980/browser/61cf2b4/cool.html?',
      $discovery->getWopiClientURL(),
    );
  }

  /**
   * Tests error behavior for blank xml content.
   */
  public function testBlankXml(): void {
    $loader = $this->getLoaderFromXml('');
    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessage('The discovery.xml file is empty.');
    $loader->getDiscovery();
  }

  /**
   * Tests error behavior for malformed xml content.
   */
  public function testBrokenXml(): void {
    $xml = 'This file does not contain valid xml.';
    $loader = $this->getLoaderFromXml($xml);
    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessageMatches('#^Error in the retrieved discovery.xml file: #');
    $loader->getDiscovery();
  }

  /**
   * Gets a discovery instance based on test xml.
   *
   * @param string $xml
   *   Explicit XML content.
   *
   * @return \Drupal\collabora_online\Discovery\DiscoveryLoader
   *   Discovery loader.
   */
  protected function getLoaderFromXml(string $xml): DiscoveryLoader {
    $fetcher = $this->getFetcherFromXml($xml);
    return new DiscoveryLoader($fetcher);
  }

  /**
   * Gets a discovery instance based on test xml.
   *
   * @param string $xml
   *   Explicit XML content.
   *
   * @return \Drupal\collabora_online\Discovery\CollaboraDiscoveryFetcherInterface
   *   Discovery fetcher.
   */
  protected function getFetcherFromXml(string $xml): CollaboraDiscoveryFetcherInterface {
    $fetcher = $this->createMock(CollaboraDiscoveryFetcherInterface::class);
    $fetcher->method('getDiscoveryXml')->willReturn($xml);
    return $fetcher;
  }

}
