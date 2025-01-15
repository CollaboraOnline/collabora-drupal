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

use Drupal\collabora_online\Cool\CollaboraDiscoveryFetcherInterface;
use Drupal\collabora_online\Cool\CollaboraDiscoveryInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @coversDefaultClass \Drupal\collabora_online\Controller\ViewerController
 */
class ViewerControllerTest extends WopiControllerTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock fetcher to get a discovery XML.
    $fetcher = $this->createMock(CollaboraDiscoveryFetcherInterface::class);
    $file = dirname(__DIR__, 3) . '/fixtures/discovery.mimetypes.xml';
    $xml = file_get_contents($file);
    $fetcher->method('getDiscoveryXml')->willReturn($xml);
    $this->container->set(CollaboraDiscoveryFetcherInterface::class, $fetcher);

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
  public function testEditorCollaboraUnavailable(): void {
    // Restore service to force fail.
    $fetcher = $this->createMock(CollaboraDiscoveryFetcherInterface::class);
    $this->container->set(CollaboraDiscoveryFetcherInterface::class, $fetcher);

    foreach ($this->createViewerRequests() as $name => $request) {
      $this->assertBadRequestResponse(
        'The Collabora Online editor/viewer is not available.',
        $request,
        [
          'message' => "Collabora Online is not available.",
          'level' => RfcLogLevel::WARNING,
        ],
        $name
      );
    }
  }

  /**
   * Tests requests with a scheme that does not match the Collabora client URL.
   *
   * @covers ::editor
   */
  public function testEditorMismatchScheme(): void {
    $wopi_url = \Drupal::service(CollaboraDiscoveryInterface::class)->getWopiClientURL();

    foreach ($this->createViewerRequests(TRUE) as $name => $request) {
      $this->assertBadRequestResponse(
        'Viewer error: Protocol mismatch.',
        $request,
        [
          'message' => "The current request uses 'https' url scheme, but the Collabora client url is '$wopi_url'.",
          'level' => RfcLogLevel::ERROR,
        ],
        $name
      );
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
        [
          'message' => 'Cannot show the viewer/editor.',
          'level' => RfcLogLevel::WARNING,
        ],
        $name
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
      ]
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
   */
  protected function assertResponseOk(Request $request, string $message = ''): void {
    $response = $this->handleRequest($request);

    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $message);
    $this->assertStringContainsString('iframe', $response->getContent(), $message);
    $this->assertEquals('', $response->headers->get('Content-Type'), $message);
  }

  /**
   * Asserts an bad request response given a request.
   *
   * @param string $expected_content
   *   The expected content.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param array $expected_log
   *   The expected log entry.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertBadRequestResponse(string $expected_content, Request $request, array $expected_log = [], string $message = ''): void {
    $this->expectException(BadRequestHttpException::class);

    $this->assertResponse(
      Response::HTTP_BAD_REQUEST,
      $expected_content,
      'text/plain',
      $request,
      $message,
    );

    if ($expected_log) {
      $this->assertTrue(
        $this->logger->hasRecord($expected_log['message'], $expected_log['level'] ?? NULL),
        sprintf('The logger does not contain a record like: "%s".', $expected_log['message'])
      );
      $this->logger->reset();
    }
  }

}
