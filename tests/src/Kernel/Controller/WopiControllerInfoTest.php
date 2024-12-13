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

use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the 'info' action in WopiController.
 *
 * @see \Drupal\collabora_online\Controller\WopiController::wopiCheckFileInfo()
 */
class WopiControllerInfoTest extends WopiControllerTestBase {

  /**
   * Tests a request with correct parameters.
   */
  public function testSuccess(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(),
    ]);

    $mtime = date_create_immutable_from_format('U', (string) $this->file->getChangedTime());

    $this->assertResponse(
      Response::HTTP_OK,
      json_encode([
        'BaseFileName' => $this->file->getFilename(),
        'Size' => $this->file->getSize(),
        'LastModifiedTime' => $mtime->format('c'),
        'UserId' => $this->user->id(),
        'UserFriendlyName' => $this->user->getDisplayName(),
        'UserExtraInfo' => [
          'mail' => $this->user->getEmail(),
        ],
        'UserCanWrite' => FALSE,
        'IsAdminUser' => FALSE,
        'IsAnonymousUser' => FALSE,
      ],
      ),
      'application/json',
      $request
    );
  }

  /**
   * Tests a request with correct parameters using write permission in payload.
   */
  public function testSuccessWrite(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(write: TRUE),
    ]);

    $mtime = date_create_immutable_from_format('U', (string) $this->file->getChangedTime());

    $this->assertResponse(
      Response::HTTP_OK,
      json_encode([
        'BaseFileName' => $this->file->getFilename(),
        'Size' => $this->file->getSize(),
        'LastModifiedTime' => $mtime->format('c'),
        'UserId' => $this->user->id(),
        'UserFriendlyName' => $this->user->getDisplayName(),
        'UserExtraInfo' => [
          'mail' => $this->user->getEmail(),
        ],
        'UserCanWrite' => TRUE,
        'IsAdminUser' => FALSE,
        'IsAnonymousUser' => FALSE,
      ]),
      'application/json',
      $request
    );
  }

  /**
   * Tests a request using deleted media.
   */
  public function testAccessDeniedMedia(): void {
    $this->media->delete();

    $request = $this->createRequest([
      'id' => '1',
      'access_token' => $this->getAccessToken(),
    ]);

    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      'Authentication failed.',
      'text/plain',
      $request
    );
  }

  /**
   * Tests a request using bad UID in payload.
   */
  public function testAccessDeniedUser(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(uid: 5),
    ]);

    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      'Authentication failed.',
      'text/plain',
      $request
    );
  }

  /**
   * Tests a request with write operation and without permission to edit.
   */
  public function testAccessDeniedWrite(): void {
    $this->user = $this->createUser(['access content']);
    $this->setCurrentUser($this->user);

    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(write: TRUE),
    ]);

    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      'Authentication failed.',
      'text/plain',
      $request
    );
    $this->assertTrue($this->logger->hasRecord('Token and user permissions do not match.'), 'error');
  }

  /**
   * {@inheritDoc}
   */
  protected function getRequestUri(): string {
    return '/cool/wopi/files/' . $this->media->id();
  }

}
