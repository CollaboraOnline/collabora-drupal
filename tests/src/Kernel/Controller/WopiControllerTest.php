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

use Drupal\Core\Logger\RfcLogLevel;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\collabora_online\Controller\WopiController
 */
class WopiControllerTest extends WopiControllerTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Disable the WOPI proof for this test.
    $this->config('collabora_online.settings')
      ->set('cool.wopi_proof', FALSE)
      ->save();
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

    // Test a successful save request without timestamp header.
    $this->doTestWopiPutFile();

    // Test a successful save request with a timestamp header.
    $headers = [];
    $headers['x-cool-wopi-timestamp'] = $file_changed_time->format(\DateTimeInterface::ATOM);
    $this->doTestWopiPutFile($headers);

    // Test how different headers result in different log messages.
    $headers['x-cool-wopi-ismodifiedbyuser'] = 'true';
    $this->doTestWopiPutFile(
      $headers,
      'Saved by Collabora Online (Modified by user)',
    );

    $headers['x-cool-wopi-isautosave'] = 'true';
    $this->doTestWopiPutFile(
      $headers,
      'Saved by Collabora Online (Modified by user, Autosaved)',
    );

    $headers['x-cool-wopi-isexitsave'] = 'true';
    $this->doTestWopiPutFile(
      $headers,
      'Saved by Collabora Online (Modified by user, Autosaved, Save on Exit)',
    );
  }

  /**
   * Does test the 'collabora-online.wopi.save' route with specific parameters.
   *
   * This is called repeatedly from the same test method, to save time.
   *
   * @param array<string, string> $request_headers
   *   Request headers.
   * @param string $reason_message
   *   Reason message expected to appear in the log and in the revision log.
   */
  protected function doTestWopiPutFile(
    array $request_headers = [],
    string $reason_message = 'Saved by Collabora Online',
  ): void {
    // The request time is always the same.
    $file_changed_time = \DateTimeImmutable::createFromFormat('U', (string) $this->file->getChangedTime());
    $expected_response_data = [
      'LastModifiedTime' => $file_changed_time->format('c'),
    ];
    $old_file = $this->loadCurrentMediaFile();
    $this->logger->reset();
    $request = $this->createRequest('/contents', 'POST', write: TRUE);
    $request->headers->add($request_headers);
    $this->assertJsonResponseOk($expected_response_data, $request);
    $this->assertTrue($this->logger->hasRecord('Save reason: ' . $reason_message));
    // Assert that a new file was created.
    $new_file = $this->loadCurrentMediaFile();
    $this->assertGreaterThan((int) $old_file->id(), (int) $new_file->id());
    $this->assertNotEquals($old_file->getFileUri(), $new_file->getFileUri());
  }

  /**
   * Tests the 'collabora-online.wopi.save' route with a conflicting timestamp.
   *
   * @covers ::wopiPutFile
   */
  public function testWopiPutFileConflict(): void {
    $request = $this->createRequest('/contents', 'POST', write: TRUE);

    $file_changed_time = \DateTimeImmutable::createFromFormat('U', (string) ($this->file->getChangedTime()));
    // Set a time in the future to force the error.
    $wopi_changed_time = \DateTimeImmutable::createFromFormat('U', (string) ($this->file->getChangedTime() + 1000));
    $request->headers->set('x-cool-wopi-timestamp', $wopi_changed_time->format(\DateTimeInterface::ATOM));

    $this->assertJsonResponse(
      Response::HTTP_CONFLICT,
      [
        'COOLStatusCode' => 1010,
      ],
      $request,
    );
    $log_record = end($this->logger->recordsByLevel[RfcLogLevel::ERROR]);
    $this->assertIsArray($log_record);
    $this->assertSame(
      'Conflict saving file for media @media_id: WOPI time @wopi_time differs from file time @file_time.',
      $log_record['message'],
    );
    $this->assertPartialArray(
      [
        '@media_id' => $this->media->id(),
        '@wopi_time' => $wopi_changed_time->format('c'),
        '@file_time' => $file_changed_time->format('c'),
      ],
      $log_record['context'],
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
      $this->assertAccessDeniedResponse(
        'Empty token values',
        $request,
        $name,
      );
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
      $this->assertAccessDeniedResponse(
        sprintf('Found fid %s in request path, but fid 4321 in token payload', $this->media->id()),
        $request,
        $name,
      );
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
      $this->assertNotFoundResponse(
        'Media not found.',
        $request,
        $name,
      );
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
      $this->assertAccessDeniedResponse(
        'User not found.',
        $request,
        $name,
      );
    }
  }

  /**
   * Asserts that an array contains a given sub-array.
   *
   * @param array $expected
   *   Expected part of the array.
   * @param mixed $actual
   *   Actual value.
   *   This is expected to be an array.
   */
  protected function assertPartialArray(array $expected, mixed $actual): void {
    $this->assertIsArray($actual);
    $this->assertSame(
      $expected,
      array_intersect_key($actual, $expected),
    );
  }

}
