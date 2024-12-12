<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Unit;

use Drupal\collabora_online\Cool\CollaboraDiscovery;
use Drupal\collabora_online\Cool\CollaboraDiscoveryFetcherInterface;
use Drupal\collabora_online\Cool\CollaboraDiscoveryInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\collabora_online\Cool\CollaboraDiscovery
 */
class CollaboraDiscoveryTest extends UnitTestCase {

  /**
   * Tests getting a client url from the discovery.xml.
   *
   * @covers ::getWopiClientURL
   */
  public function testWopiClientUrl(): void {
    $file = dirname(__DIR__, 2) . '/fixtures/discovery.mimetypes.xml';
    $discovery = $this->getDiscoveryFromFile($file);
    $this->assertSame(
      'http://collabora.test:9980/browser/61cf2b4/cool.html?',
      $discovery->getWopiClientURL(),
    );
    $this->assertSame(
      'http://spreadsheet.collabora.test:9980/browser/61cf2b4/cool.html?',
      $discovery->getWopiClientURL('text/spreadsheet'),
    );
    // Test unknown mime type.
    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessage('The requested mime type is not handled.');
    $discovery->getWopiClientURL('text/unknown');
  }

  /**
   * Tests error behavior for blank xml content.
   *
   * @covers ::getWopiClientURL
   */
  public function testBlankXml(): void {
    $discovery = $this->getDiscoveryFromXml('');

    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessage('The discovery.xml file is empty.');
    $discovery->getWopiClientURL();
  }

  /**
   * Tests error behavior for malformed xml content.
   *
   * @covers ::getWopiClientURL
   */
  public function testBrokenXml(): void {
    $xml = 'This file does not contain valid xml.';
    $discovery = $this->getDiscoveryFromXml($xml);

    $this->expectException(CollaboraNotAvailableException::class);
    $this->expectExceptionMessageMatches('#^Error in the retrieved discovery.xml file: #');
    $discovery->getWopiClientURL();
  }

  /**
   * Gets a discovery instance based on a test xml file.
   *
   * @param string $file
   *   A test xml file.
   *
   * @return \Drupal\collabora_online\Cool\CollaboraDiscoveryInterface
   *   Discovery instance.
   */
  protected function getDiscoveryFromFile(string $file): CollaboraDiscoveryInterface {
    $xml = file_get_contents($file);
    return $this->getDiscoveryFromXml($xml);
  }

  /**
   * Gets a discovery instance based on test xml.
   *
   * @param string $xml
   *   Explicit XML content.
   *
   * @return \Drupal\collabora_online\Cool\CollaboraDiscoveryInterface
   *   Discovery instance.
   */
  protected function getDiscoveryFromXml(string $xml): CollaboraDiscoveryInterface {
    $fetcher = $this->createMock(CollaboraDiscoveryFetcherInterface::class);
    $fetcher->method('getDiscoveryXml')->willReturn($xml);
    return new CollaboraDiscovery($fetcher);
  }

}
