<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Unit;

use Drupal\collabora_online\Discovery\Discovery;
use Drupal\collabora_online\Discovery\DiscoveryInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * @coversDefaultClass \Drupal\collabora_online\Discovery\Discovery
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
    $this->assertNull(
      $discovery->getWopiClientURL('text/unknown'),
    );
  }

  /**
   * Tests reading proof keys from the discovery.xml.
   *
   * @covers ::getProofKey
   * @covers ::getProofKeyOld
   */
  public function testProofKey(): void {
    $file = dirname(__DIR__, 2) . '/fixtures/discovery.proof-key.xml';
    $discovery = $this->getDiscoveryFromFile($file);
    $this->assertSame(
      'BgIAAACkAABSU0ExAAgAAAEAAQCxJYuefceQ4XI3/iUQvL9+ebLFZSRdM1n6fkB+OtILJexHUsD/aItTWgzB/G6brdxLlyHXoPjbJl4QoWZVrr1XY+ZHQ/a9Yzf/VN2mPLKFB9hmnUPI580VpFfkC3gCgpqwFwMpAkQSzYSDFQ/7W4ryPP6irvVzhg16IqQ9oEhZWmwy6caKcqh4BK31oI8SrI6bsZLBMTli70197UWHmgIGk4JJbeC8cBFb6uZDaidAcRn1HSAF2JnaEscUNMIsiNMM/71BT6U6hVSv5Qk0oISMLfVOeCPQZ6OmYo4M42wDKBpaJGMOpgoeQX6Feq+agf7uBvd8S/ITGZ8WinQfHZaQ',
      $discovery->getProofKey(),
    );
    $this->assertSame(
      'BgIAAACkAABSU0ExAAgAAAEAAQDj9QjZQ9bOOw5LfAMxMLMDTLgHsNvBcdRpYQ8S9qK9ylJevgp+j66k9/uyKXSwI9WTVHW+XLTCPq6aId+XqB5e8+H5rov7e4Itkpnr6eXZ1jAu9TW2jEnqCYdGqG6Pv0kbRv1gUFEsjciy8i9UAQ12Ons7J58nQLd3tJ4WATANoCyVJLfA7BQ6IRSq8/K3jqmSE8xu3HDLX+lnMrsK2KL4lYcjerGZpmOKI5tPZbC5xSMkB9alE5NhTYeYw25CyG4FHoss2AwNgvSQDaf6d/icNg5ZoGQwtISGKL6IFc4oogFHFdvR4FQCQ61wdz7RmHjJUpsPFio8htuSeMjbC7fS',
      $discovery->getProofKeyOld(),
    );
  }

  /**
   * Tests behavior if discovery.xml does not have proof keys.
   *
   * @covers ::getProofKey
   * @covers ::getProofKeyOld
   */
  public function testNoProofKey(): void {
    $xml = '<wopi-discovery></wopi-discovery>';
    $discovery = $this->getDiscoveryFromXml($xml);
    $this->assertNull($discovery->getProofKey());
    $this->assertNull($discovery->getProofKeyOld());
  }

  /**
   * Gets a discovery instance based on a test xml file.
   *
   * @param string $file
   *   A test xml file.
   *
   * @return \Drupal\collabora_online\Discovery\DiscoveryInterface
   *   Discovery instance.
   */
  protected function getDiscoveryFromFile(string $file): DiscoveryInterface {
    $xml = file_get_contents($file);
    return $this->getDiscoveryFromXml($xml);
  }

  /**
   * Gets a discovery instance based on test xml.
   *
   * @param string $xml
   *   Explicit XML content.
   *
   * @return \Drupal\collabora_online\Discovery\DiscoveryInterface
   *   Discovery instance.
   */
  protected function getDiscoveryFromXml(string $xml): DiscoveryInterface {
    /** @var \SimpleXMLElement|false $parsed_xml */
    $parsed_xml = ErrorHandler::call(
      fn () => simplexml_load_string($xml),
    );
    $this->assertNotFalse($parsed_xml, "XML: '$xml'");
    return new Discovery($parsed_xml);
  }

}
