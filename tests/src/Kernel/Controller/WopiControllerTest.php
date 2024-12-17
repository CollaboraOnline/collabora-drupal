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

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\collabora_online\Kernel\CollaboraKernelTestBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\collabora_online\Controller\WopiController
 */
class WopiControllerTest extends CollaboraKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online_test',
  ];

  /**
   * The user with access to perform operations.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * The media where to perform operations.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected MediaInterface $media;

  /**
   * The source file.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface $file;

  /**
   * The test logger channel.
   *
   * @var \ColinODell\PsrTestLogger\TestLogger
   */
  protected TestLogger $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->logger = new TestLogger();
    \Drupal::service('logger.factory')->addLogger($this->logger);

    $collabora_settings = \Drupal::configFactory()->getEditable('collabora_online.settings');
    $cool = $collabora_settings->get('cool');
    $cool['key_id'] = 'collabora';
    $collabora_settings->set('cool', $cool)->save();

    // Make sure that ids for different entity types are distinguishable.
    // This will reveal bugs where one id gets mixed up for another.
    \Drupal::database()->query("ALTER TABLE {media} AUTO_INCREMENT = 1000");
    \Drupal::database()->query("ALTER TABLE {file_managed} AUTO_INCREMENT = 2000");

    $this->media = $this->createMediaEntity('document');
    $this->user = $this->createUser([
      'access content',
      'edit any document in collabora',
    ]);
    $fid = $this->media->getSource()->getSourceFieldValue($this->media);
    $this->file = File::load($fid);

    $this->setCurrentUser($this->user);
  }

  /**
   * Tests successful requests to the 'collabora-online.wopi.info' route.
   *
   * @covers ::wopiCheckFileInfo
   */
  public function testWopiGetFileInfo(): void {
    $file_changed_time = \DateTimeImmutable::createFromFormat('U', (string) $this->file->getChangedTime());
    $expected_response_data = [
      'BaseFileName' => $this->file->getFilename(),
      'Size' => $this->file->getSize(),
      'LastModifiedTime' => $file_changed_time->format('c'),
      'UserId' => $this->user->id(),
      'UserFriendlyName' => $this->user->getDisplayName(),
      'UserExtraInfo' => [
        'mail' => $this->user->getEmail(),
      ],
      'UserCanWrite' => FALSE,
      'IsAdminUser' => FALSE,
      'IsAnonymousUser' => FALSE,
    ];

    $request = $this->createRequest();
    $this->assertJsonResponseOk($expected_response_data, $request);

    $request = $this->createRequest(write: TRUE);
    $expected_response_data['UserCanWrite'] = TRUE;
    $this->assertJsonResponseOk($expected_response_data, $request);
  }

  /**
   * Tests successful requests to the 'collabora-online.wopi.contents' route.
   *
   * @covers ::wopiGetFile
   */
  public function testWopiGetFile(): void {
    $request = $this->createRequest(
      '/contents',
    );
    $response = $this->handleRequest($request);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertEquals($this->file->getFileUri(), $response->getFile());
    $this->assertEquals($this->file->getMimeType(), $response->headers->get('Content-Type'));
  }

  /**
   * Tests successful requests to the 'collabora-online.wopi.save' route.
   *
   * @covers ::wopiPutFile
   */
  public function testWopiPutFile(): void {
    $file_changed_time = \DateTimeImmutable::createFromFormat('U', (string) $this->file->getChangedTime());
    $expected_response_data = [
      'LastModifiedTime' => $file_changed_time->format('c'),
    ];
    $assert_response = function (Request $request, string $log_message) use ($expected_response_data): void {
      $this->logger->reset();
      $this->assertJsonResponseOk($expected_response_data, $request);
      $log_message ??= 'Save reason: Saved by Collabora Online';
      $this->assertTrue($this->logger->hasRecord($log_message), 'error');
    };

    // Test a successful save request without timestamp header.
    $request = $this->createRequest('/contents', 'POST', write: TRUE);
    $log_message = 'Save reason: Saved by Collabora Online';
    $assert_response($request, $log_message);

    // Test a successful save request with a timestamp header.
    $request->headers->set('x-cool-wopi-timestamp', $file_changed_time->format(\DateTimeInterface::ATOM));
    $assert_response($request, $log_message);

    // Test how different headers result in different log messages.
    $request->headers->set('x-cool-wopi-ismodifiedbyuser', 'true');
    $log_message = 'Save reason: Saved by Collabora Online (Modified by user)';
    $assert_response($request, $log_message);

    $request->headers->set('x-cool-wopi-isautosave', 'true');
    $log_message = 'Save reason: Saved by Collabora Online (Modified by user, Autosaved)';
    $assert_response($request, $log_message);

    $request->headers->set('x-cool-wopi-isexitsave', 'true');
    $log_message = 'Save reason: Saved by Collabora Online (Modified by user, Autosaved, Save on Exit)';
    $assert_response($request, $log_message);
  }

  /**
   * Tests the 'collabora-online.wopi.save' route with a conflicting timestamp.
   *
   * @covers ::wopiPutFile
   */
  public function testWopiPutFileConflict(): void {
    $request = $this->createRequest('/contents', 'POST', write: TRUE);

    // Set a time in the future to force the error.
    $file_changed_time = \DateTimeImmutable::createFromFormat('U', (string) ($this->file->getChangedTime() + 1000));
    $request->headers->set('x-cool-wopi-timestamp', $file_changed_time->format(\DateTimeInterface::ATOM));

    $this->assertJsonResponse(
      Response::HTTP_CONFLICT,
      [
        'COOLStatusCode' => 1010,
      ],
      $request
    );
  }

  /**
   * Tests different routes using an invalid token.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testBadToken(): void {
    $requests = $this->createRequests();
    foreach ($requests as $name => $request) {
      // Replace the token with a value that is not in the JWT format.
      $request->query->set('access_token', 'bad_token');
      $this->assertAccessDeniedResponse($request, $name);
    }
  }

  /**
   * Tests different routes using the wrong token payload values.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testWrongTokenPayload(): void {
    // Inject a bad value into the token payload.
    $requests = $this->createRequests(token_payload: ['fid' => 4321]);
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse($request, $name);
    }
  }

  /**
   * Tests different routes using a non-existing media id.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testMediaNotFound(): void {
    $requests = $this->createRequests(media_id: 555);
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse($request, $name);
    }
  }

  /**
   * Tests different routes using a non-existing user id.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testUserNotFound(): void {
    $requests = $this->createRequests(user_id: 555);
    unset($requests['file']);
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse($request, $name);
    }
  }

  /**
   * Creates WOPI requests for different routes, with some shared parameters.
   *
   * This can be used for tests where each route is expected to have the same
   * response.
   *
   * @param int|null $media_id
   *   Media entity id, if different from the default.
   * @param int|null $user_id
   *   User id, if different from the default.
   * @param array $token_payload
   *   Explicit token payload values.
   *   This can be used to cause a bad token.
   *
   * @return array<string, \Symfony\Component\HttpFoundation\Request>
   *   Requests keyed by a distinguishable name.
   */
  protected function createRequests(
    ?int $media_id = NULL,
    ?int $user_id = NULL,
    array $token_payload = [],
  ): array {
    $create_request = fn (string $uri_suffix, string $method = 'GET', bool $write = FALSE) => $this->createRequest(
      $uri_suffix,
      $method,
      $media_id,
      $user_id,
      $write,
      $token_payload,
    );
    return [
      'info' => $create_request(''),
      'file' => $create_request('/contents'),
      'save' => $create_request('/contents', 'POST', TRUE),
    ];
  }

  /**
   * Creates a WOPI request.
   *
   * @param string $uri_suffix
   *   Suffix to append to the WOPI media url.
   * @param string $method
   *   E.g. 'GET' or 'POST'.
   * @param int|null $media_id
   *   Media entity id, if different from the default.
   * @param int|null $user_id
   *   User id, if different from the default.
   * @param bool $write
   *   TRUE if write access is requested.
   * @param array $token_payload
   *   Explicit token payload values.
   *   This can be used to cause a bad token.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createRequest(
    string $uri_suffix = '',
    string $method = 'GET',
    ?int $media_id = NULL,
    ?int $user_id = NULL,
    bool $write = FALSE,
    array $token_payload = [],
  ): Request {
    $media_id ??= (int) $this->media->id();
    $user_id ??= (int) $this->user->id();
    $uri = '/cool/wopi/files/' . $media_id . $uri_suffix;
    $token = $this->createAccessToken($media_id, $user_id, $write, $token_payload);
    $parameters = [
      'id' => $media_id,
      'access_token' => $token,
    ];
    return Request::create($uri, $method, $parameters);
  }

  /**
   * Retrieves an encoded access token.
   *
   * @param int|null $fid
   *   The file id.
   * @param int|null $uid
   *   The user id.
   * @param bool $write
   *   The write permission.
   * @param array $payload
   *   Explicit payload values.
   *   This can be used to cause a bad token.
   *
   * @return string
   *   The enconded token.
   */
  protected function createAccessToken(?int $fid = NULL, ?int $uid = NULL, bool $write = FALSE, array $payload = []): string {
    /** @var \Drupal\collabora_online\Jwt\JwtTranscoderInterface $transcoder */
    $transcoder = \Drupal::service(JwtTranscoderInterface::class);
    $expire_timestamp = gettimeofday(TRUE) + 1000;
    $payload += [
      'fid' => (string) ($fid ?? $this->media->id()),
      'uid' => (string) ($uid ?? $this->user->id()),
      'wri' => $write,
      'exp' => $expire_timestamp,
    ];
    return $transcoder->encode($payload, $expire_timestamp);
  }

  /**
   * Asserts status code and content in a response given a request.
   *
   * @param array $expected_data
   *   The expected response JSON data.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertJsonResponseOk(array $expected_data, Request $request, string $message = ''): void {
    $this->assertJsonResponse(Response::HTTP_OK, $expected_data, $request, $message);
  }

  /**
   * Asserts a json response given a request.
   *
   * @param int $expected_code
   *   The expected response status code.
   * @param array $expected_data
   *   The expected response JSON data.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertJsonResponse(int $expected_code, array $expected_data, Request $request, string $message = ''): void {
    $response = $this->handleRequest($request);
    $this->assertEquals($expected_code, $response->getStatusCode(), $message);
    $this->assertEquals('application/json', $response->headers->get('Content-Type'), $message);
    $content = $response->getContent();
    $data = Json::decode($content);
    $this->assertSame($expected_data, $data, $message);
  }

  /**
   * Asserts an access denied response given a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertAccessDeniedResponse(Request $request, string $message = ''): void {
    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      'Authentication failed.',
      'text/plain',
      $request,
      $message,
    );
  }

  /**
   * Asserts status code and content in a response given a request.
   *
   * @param int $expected_code
   *   The expected response status code.
   * @param string $expected_content
   *   The expected response content.
   * @param string $expected_content_type
   *   The type of content of the response.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertResponse(int $expected_code, string $expected_content, string $expected_content_type, Request $request, string $message = ''): void {
    $response = $this->handleRequest($request);

    $this->assertEquals($expected_code, $response->getStatusCode(), $message);
    $this->assertEquals($expected_content, $response->getContent(), $message);
    $this->assertEquals($expected_content_type, $response->headers->get('Content-Type'), $message);
  }

  /**
   * Handles a request and gets the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Incoming request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  protected function handleRequest(Request $request): Response {
    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::service('http_kernel');
    return $kernel->handle($request);
  }

}
