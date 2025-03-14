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

namespace Drupal\Tests\collabora_online\Kernel\Controller;

use Drupal\collabora_online\Discovery\DiscoveryFetcherInterface;
use Drupal\collabora_online\Discovery\DiscoveryInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\collabora_online\Controller\ViewerController
 */
class ViewerControllerTest extends WopiControllerTestBase {

  /**
   * Return value for $discovery->getWopiClientUrl().
   *
   * @var string|null
   */
  protected ?string $mockWopiClientUrl;

  /**
   * Callback to replace CollaboraDiscoveryFetcher->getDiscovery().
   *
   * @var \Closure(): \Drupal\collabora_online\Discovery\DiscoveryInterface
   */
  protected \Closure $mockGetDiscovery;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->mockWopiClientUrl = 'http://collabora.test:9980/browser/61cf2b4/cool.html';

    // Set a mock discovery with custom proof keys.
    $mock_discovery = $this->createMock(DiscoveryInterface::class);
    $mock_discovery->method('getWopiClientURL')
      ->willReturnCallback(fn () => $this->mockWopiClientUrl);
    $this->mockGetDiscovery = fn () => $mock_discovery;

    $mock_discovery_fetcher = $this->createMock(DiscoveryFetcherInterface::class);
    $mock_discovery_fetcher->method('getDiscovery')->willReturnCallback(fn () => ($this->mockGetDiscovery)());
    $this->container->set(DiscoveryFetcherInterface::class, $mock_discovery_fetcher);

    $this->user = $this->createUser([
      'access content',
      'preview document in collabora',
      'edit any document in collabora',
    ]);

    $this->setCurrentUser($this->user);
  }

  /**
   * Tests successful requests.
   *
   * @covers ::editor
   */
  public function testEditorSuccess(): void {
    foreach ($this->createViewerRequests() as $name => $request) {
      $this->assertResponseOk($request, $name);
    }
  }

  /**
   * Tests requests with Collabora unavailable.
   *
   * @covers ::editor
   */
  public function testEditorDiscoveryNotAvailable(): void {
    // Let the discovery throw an exception.
    // The message is custom, and does not appear in the real codebase.
    $this->mockGetDiscovery = fn () => throw new CollaboraNotAvailableException(
      'The discovery.xml cannot be loaded or is malformed.',
    );

    foreach ($this->createViewerRequests() as $name => $request) {
      $this->assertBadRequestResponse(
        'The Collabora Online editor/viewer is not available.',
        $request,
        $name,
      );
      $this->assertLogMessage(
        RfcLogLevel::WARNING,
        "Collabora Online is not available.<br>\n" . Error::DEFAULT_ERROR_MESSAGE,
        [
          '%type' => CollaboraNotAvailableException::class,
          '@message' => 'The discovery.xml cannot be loaded or is malformed.',
        ],
        assertion_message: $name,
      );
    }
  }

  /**
   * Tests requests with a scheme that does not match the Collabora client URL.
   *
   * @covers ::editor
   */
  protected function testEditorMismatchScheme(): void {
    $cases = [
      ['https', 'http://collabora.test:9980/browser/61cf2b4/cool.html'],
      ['http', 'https://collabora.test:9980/browser/61cf2b4/cool.html'],
    ];
    foreach ($cases as [$request_scheme, $client_url]) {
      $requests = $this->createViewerRequests($request_scheme === 'https');
      $this->mockWopiClientUrl = $client_url;
      foreach ($requests as $name => $request) {
        $this->assertBadRequestResponse(
          'Viewer error: Protocol mismatch.',
          $request,
          $name,
        );
        $this->assertLogMessage(
          RfcLogLevel::ERROR,
          "The current request uses '@current_request_scheme' url scheme, but the Collabora client url is '@wopi_client_url'.",
          [
            '@current_request_scheme' => $request_scheme,
            '@wopi_client_url' => $this->mockWopiClientUrl,
          ],
          assertion_message: $name,
        );
      }
    }
  }

  /**
   * Tests requests with missing configuration.
   *
   * @covers ::editor
   */
  public function testEditorMissingConfiguration(): void {
    // Delete the configuration to force a fail.
    \Drupal::configFactory()
      ->getEditable('collabora_online.settings')
      ->delete();

    foreach ($this->createViewerRequests() as $name => $request) {
      $this->assertBadRequestResponse(
        'The Collabora Online editor/viewer is not available.',
        $request,
        $name,
      );
      $this->assertLogMessage(
        RfcLogLevel::WARNING,
        "Cannot show the viewer/editor.<br>\n" . Error::DEFAULT_ERROR_MESSAGE,
        [
          '%type' => CollaboraNotAvailableException::class,
          '@message' => 'The Collabora Online connection is not configured.',
        ],
        assertion_message: $name,
      );
    }
  }

  /**
   * Tests requests when the WOPI client url is empty.
   *
   * @covers ::editor
   */
  public function testWopiClientUrlEmpty(): void {
    $this->mockWopiClientUrl = NULL;
    foreach ($this->createViewerRequests() as $name => $request) {
      $this->assertBadRequestResponse(
        'The Collabora Online editor/viewer is not available for this file type.',
        $request,
        $name,
      );
    }
  }

  /**
   * Tests that trailing slashes in the WOPI url are normalized.
   *
   * @covers ::getViewerRender
   */
  public function testWopiBaseTrailingSlashes(): void {
    // Set empty configuration to force fail.
    $config = \Drupal::configFactory()->getEditable('collabora_online.settings');
    // Form URL fields allow any number of trailing slashes.
    $slash_cases = ['', '/', '//'];
    foreach ($slash_cases as $slash_case_name => $slash) {
      $wopi_url = "https://wopi.$slash_case_name.example.com";
      $config->set('cool.wopi_base', $wopi_url . $slash)->save();
      foreach ($this->createViewerRequests() as $request_name => $request) {
        $response = $this->assertResponseOk($request, $request_name);
        // Verify that the slashes are normalized.
        $this->assertStringContainsString(
          urlencode($wopi_url . '/cool/wopi/files/'),
          $response->getContent(),
          $request_name . ' / ' . $slash_case_name,
        );
        // Verify that only one such path exists in the response body.
        $this->assertSame(
          1,
          substr_count($response->getContent(), urlencode('cool/wopi/files')),
          $request_name . ' / ' . $slash_case_name,
        );
      }
    }
  }

  /**
   * Tests requests with a viewer not available.
   *
   * @covers ::editor
   */
  public function testEditorNoViewer(): void {
    // Mock transcoder to force fail.
    $transcoder = $this->createMock(jwtTranscoderInterface::class);
    $transcoder->method('encode')->willThrowException(new CollaboraNotAvailableException());
    $this->container->set(jwtTranscoderInterface::class, $transcoder);

    foreach ($this->createViewerRequests() as $name => $request) {
      $this->assertBadRequestResponse(
        'The Collabora Online editor/viewer is not available.',
        $request,
        $name,
      );
      $this->assertLogMessage(
        RfcLogLevel::WARNING,
        "Cannot show the viewer/editor.<br>\n" . Error::DEFAULT_ERROR_MESSAGE,
        [
          '%type' => CollaboraNotAvailableException::class,
          '@message' => '',
        ],
        assertion_message: $name,
      );
    }
  }

  /**
   * Creates requests for different routes, with some shared parameters.
   *
   * @param bool $https
   *   If the requests are secure.
   *
   * @return array<string, \Symfony\Component\HttpFoundation\Request>
   *   Requests keyed by a distinguishable name.
   */
  protected function createViewerRequests($https = FALSE): array {
    return [
      'view' => $this->createViewerRequest('view', $https),
      'edit' => $this->createViewerRequest('edit', $https),
    ];
  }

  /**
   * Creates a view/edit request.
   *
   * @param string $action
   *   View or edit the media.
   * @param bool $https
   *   If the request is secure.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createViewerRequest(string $action, bool $https): Request {
    $url = Url::fromRoute(
      "collabora-online.$action",
      [
        'media' => $this->media->id(),
      ],
      [
        'https' => $https,
        'absolute' => TRUE,
      ],
    );

    return Request::create($url->toString());
  }

  /**
   * Asserts an successful response given a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object for further checks.
   */
  protected function assertResponseOk(Request $request, string $message = ''): Response {
    $response = $this->handleRequest($request);

    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $message);
    $this->assertStringContainsString('iframe', $response->getContent(), $message);
    $this->assertEquals('', $response->headers->get('Content-Type'), $message);

    return $response;
  }

  /**
   * Asserts an bad request response given a request.
   *
   * @param string $expected_content
   *   The expected content.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertBadRequestResponse(string $expected_content, Request $request, string $message = ''): void {
    $this->assertResponse(
      Response::HTTP_BAD_REQUEST,
      $expected_content,
      'text/plain',
      $request,
      $message,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function assertPostConditions(): void {
    parent::assertPostConditions();
    $this->assertNoFurtherLogMessages();
  }

}
