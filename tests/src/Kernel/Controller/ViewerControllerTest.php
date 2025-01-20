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
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @coversDefaultClass \Drupal\collabora_online\Controller\ViewerController
 */
class ViewerControllerTest extends WopiControllerTestBase {

  /**
   * Expected logs after test execution.
   *
   * @var array
   */
  protected $expected_logs = [];

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
    foreach (self::actionsDataProvider()[0] as $action) {
      $request = $this->createViewerRequest($action);
      $this->assertResponseOk(
        $request,
        sprintf('Failed asserting the request to %s the media', $action));
    }
  }

  /**
   * Tests requests with Collabora unavailable.
   *
   * @covers ::editor
   * @dataProvider actionsDataProvider
   */
  public function testEditorCollaboraUnavailable(string $action): void {
    // Restore service to force fail.
    $fetcher = $this->createMock(CollaboraDiscoveryFetcherInterface::class);
    $this->container->set(CollaboraDiscoveryFetcherInterface::class, $fetcher);

    $request = $this->createViewerRequest($action);

    $this->assertBadRequestResponse(
      'The Collabora Online editor/viewer is not available.',
      $request,
    );
  }

  /**
   * Tests requests with a scheme that does not match the Collabora client URL.
   *
   * @covers ::editor
   * @dataProvider actionsDataProvider
   */
  public function testEditorMismatchScheme(string $action): void {
    $request = $this->createViewerRequest($action, TRUE);

    $this->assertBadRequestResponse(
      'Viewer error: Protocol mismatch.',
      $request,
    );
  }

  /**
   * Tests requests with missing configuration.
   *
   * @covers ::editor
   * @dataProvider actionsDataProvider
   */
  public function testEditorMissingConfiguration(string $action): void {
    // Set empty configuration to force fail.
    $config = \Drupal::configFactory()->getEditable('collabora_online.settings');
    $config->set('cool', [])->save();

    $request = $this->createViewerRequest($action);

    $this->assertBadRequestResponse(
      'The Collabora Online editor/viewer is not available.',
      $request,
    );
  }

  /**
   * Tests requests with a viewer not available.
   *
   * @covers ::editor
   * @dataProvider actionsDataProvider
   */
  public function testEditorNoViewer(string $action): void {
    // Mock transcoder to force fail.
    $transcoder = $this->createMock(jwtTranscoderInterface::class);
    $transcoder->method('encode')->willThrowException(new CollaboraNotAvailableException());
    $this->container->set(jwtTranscoderInterface::class, $transcoder);

    $request = $this->createViewerRequest($action);

    $this->assertBadRequestResponse(
      'The Collabora Online editor/viewer is not available.',
      $request,
    );
  }

  /**
   * Provides data with actions available.
   *
   * @return array
   *   The list of actions.
   */
  public static function actionsDataProvider(): array {
    return [
      ['view'],
      ['edit'],
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
  protected function createViewerRequest(string $action, bool $https = FALSE): Request {
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
   */
  protected function assertBadRequestResponse(string $expected_content, Request $request): void {
    $this->expectExceptionObject(new BadRequestHttpException($expected_content));

    $this->expected_logs = [
      [
        'level' => RfcLogLevel::WARNING,
        'message' => Error::DEFAULT_ERROR_MESSAGE,
        'replacements' => [
          '@message' => $expected_content,
        ],
        'channel' => 'client error',
      ],
    ];

    $this->handleRequest($request);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertPostConditions(): void {
    // In case exception we want to make sure the logs are added.
    foreach ($this->expected_logs as $log) {
      $this->assertLogMessage(
        $log['level'] ?? NULL,
        $log['message'] ?? NULL,
        $log['replacements'] ?? [],
        $log['channel'] ?? 'cool',
        $log['position'] ?? 0,
        $log['assertion_message'] ?? '',
      );
    }
    $this->assertNoFurtherLogMessages();
  }

}
