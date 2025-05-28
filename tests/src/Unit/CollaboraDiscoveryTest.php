<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Unit;

use Drupal\collabora_online\Discovery\Discovery;
use Drupal\collabora_online\Discovery\DiscoveryInterface;
use Drupal\Core\Serialization\Yaml;
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
      $discovery->getWopiClientURL('text/plain', 'view'),
    );
    $this->assertSame(
      'http://spreadsheet.collabora.test:9980/browser/61cf2b4/cool.html?',
      $discovery->getWopiClientURL('text/spreadsheet', 'edit'),
    );
    // Test unknown mime type.
    $this->assertNull($discovery->getWopiClientURL('text/unknown', 'view'));
    $this->assertSame(
      'http://csv.collabora.test:9980/browser/61cf2b4/cool.html?',
      $discovery->getWopiClientURL('text/csv', 'edit'),
    );
    $this->assertSame(
      'http://view.csv.collabora.test:9980/browser/61cf2b4/cool.html?',
      $discovery->getWopiClientURL('text/csv', 'view'),
    );
    // Test the default MIME type 'text/plain' which has only 'edit' action in
    // the example file, but no 'view' action.
    $this->assertNull($discovery->getWopiClientURL('text/plain', 'edit'));
    $this->assertNotNull($discovery->getWopiClientURL('text/plain', 'view'));
    // Test a MIME type with no action name specified.
    // This does not occur in the known discovery.xml, but we still want a
    // well-defined behavior in that case.
    $this->assertNull($discovery->getWopiClientURL('image/png', 'edit'));
    $this->assertNull($discovery->getWopiClientURL('image/png', 'view'));
  }

  /**
   * Tests which MIME types are supported in a realistic discovery.xml.
   *
   * That file was generated with Collabora, but may not be the same in the
   * latest version.
   */
  public function testRealisticDiscoveryXml(): void {
    $file = dirname(__DIR__, 2) . '/fixtures/discovery.xml';
    $xml = file_get_contents($file);
    $this->assertSame(98, preg_match_all('@<app( [^>]+)* name="([^"]+)"@', $xml, $matches));
    $this->assertSame('application/vnd.ms-excel', $matches[2][9]);
    $mimetypes = array_unique($matches[2]);
    $mimetypes = array_diff($mimetypes, ['Capabilities']);
    sort($mimetypes);
    $discovery = $this->getDiscoveryFromXml($xml);
    $known_url = 'http://collabora.test:9980/browser/61cf2b4/cool.html?';
    $supported_action_types = [];
    foreach ($mimetypes as $mimetype) {
      $type_supported_actions = [];
      foreach (['edit', 'view_comment', 'view'] as $action) {
        $url = $discovery->getWopiClientURL($mimetype, $action);
        if ($url !== NULL) {
          $this->assertSame($known_url, $url);
          $type_supported_actions[] = $action;
        }
      }
      sort($type_supported_actions);
      $supported_action_types[implode(',', $type_supported_actions)][] = $mimetype;
    }
    ksort($supported_action_types);
    $this->assertSame([
      '' => [
        'application/vnd.oasis.opendocument.formula',
        'application/vnd.sun.xml.math',
        'math',
      ],
      'edit' => [
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
        'application/vnd.ms-excel.sheet.macroEnabled.12',
        'application/vnd.ms-powerpoint',
        'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
        'application/vnd.ms-word.document.macroEnabled.12',
        'application/vnd.oasis.opendocument.chart',
        'application/vnd.oasis.opendocument.graphics',
        'application/vnd.oasis.opendocument.graphics-flat-xml',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.presentation-flat-xml',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.text-flat-xml',
        'application/vnd.oasis.opendocument.text-master',
        'application/vnd.oasis.opendocument.text-web',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.sun.xml.draw',
        'application/x-dbase',
        'application/x-dif-document',
        'text/csv',
        'text/plain',
        'text/rtf',
        'text/spreadsheet',
        'writer-web',
      ],
      'edit,view' => [
        'calc',
        'impress',
        'writer',
        'writer-global',
      ],
      'edit,view,view_comment' => [
        'draw',
      ],
      'view' => [
        'application/clarisworks',
        'application/coreldraw',
        'application/macwriteii',
        'application/vnd.lotus-1-2-3',
        'application/vnd.ms-excel.template.macroEnabled.12',
        'application/vnd.ms-powerpoint.template.macroEnabled.12',
        'application/vnd.ms-visio.drawing',
        'application/vnd.ms-word.template.macroEnabled.12',
        'application/vnd.ms-works',
        'application/vnd.oasis.opendocument.graphics-template',
        'application/vnd.oasis.opendocument.presentation-template',
        'application/vnd.oasis.opendocument.spreadsheet-template',
        'application/vnd.oasis.opendocument.text-master-template',
        'application/vnd.oasis.opendocument.text-template',
        'application/vnd.openxmlformats-officedocument.presentationml.template',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'application/vnd.sun.xml.calc',
        'application/vnd.sun.xml.calc.template',
        'application/vnd.sun.xml.chart',
        'application/vnd.sun.xml.draw.template',
        'application/vnd.sun.xml.impress',
        'application/vnd.sun.xml.impress.template',
        'application/vnd.sun.xml.writer',
        'application/vnd.sun.xml.writer.global',
        'application/vnd.sun.xml.writer.template',
        'application/vnd.visio',
        'application/vnd.visio2013',
        'application/vnd.wordperfect',
        'application/x-abiword',
        'application/x-aportisdoc',
        'application/x-fictionbook+xml',
        'application/x-gnumeric',
        'application/x-hwp',
        'application/x-iwork-keynote-sffkey',
        'application/x-iwork-numbers-sffnumbers',
        'application/x-iwork-pages-sffpages',
        'application/x-mspublisher',
        'application/x-mswrite',
        'application/x-pagemaker',
        'application/x-sony-bbeb',
        'application/x-t602',
        'image/bmp',
        'image/cgm',
        'image/gif',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/svg+xml',
        'image/tiff',
        'image/vnd.dxf',
        'image/x-emf',
        'image/x-freehand',
        'image/x-wmf',
        'image/x-wpg',
      ],
      'view_comment' => [
        'application/pdf',
      ],
    ], $supported_action_types);
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
