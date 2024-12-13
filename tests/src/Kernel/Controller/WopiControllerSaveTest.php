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
 * Tests the 'save' action in WopiController.
 *
 * @see \Drupal\collabora_online\Controller\WopiController::wopiPutFile()
 */
class WopiControllerSaveTest extends WopiControllerTestBase {

  /**
   * {@inheritDoc}
   */
  const METHOD = 'POST';

  /**
   * Tests a request with correct parameters.
   */
  public function testSuccess(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(write: TRUE),
    ]);

    $mtime = date_create_immutable_from_format('U', (string) $this->file->getChangedTime());

    $this->assertResponse(
      Response::HTTP_OK,
      json_encode([
        'LastModifiedTime' => $mtime->format('c'),
      ]),
      'application/json',
      $request
    );
    $this->assertTrue($this->logger->hasRecord('Save reason: Saved by Collabora Online'), 'error');
  }

  /**
   * Tests a request with correct parameters and timestamp value in the header.
   */
  public function testSuccessTimestamp(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(write: TRUE),
    ]);

    $mtime = date_create_immutable_from_format('U', (string) $this->file->getChangedTime());
    $request->headers->set('x-cool-wopi-timestamp', $mtime->format(\DateTimeInterface::ATOM));

    $this->assertResponse(
      Response::HTTP_OK,
      json_encode([
        'LastModifiedTime' => $mtime->format('c'),
      ]),
      'application/json',
      $request
    );
    $this->assertTrue($this->logger->hasRecord('Save reason: Saved by Collabora Online'), 'error');
  }

  /**
   * Tests a request with correct parameters and all parameters in header.
   */
  public function testSuccessAllParameters(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(write: TRUE),
    ]);

    $mtime = date_create_immutable_from_format('U', (string) $this->file->getChangedTime());
    $request->headers->set('x-cool-wopi-timestamp', $mtime->format(format: \DateTimeInterface::ATOM));
    $request->headers->set('x-cool-wopi-ismodifiedbyuser', 'true');
    $request->headers->set('x-cool-wopi-isautosave', 'true');
    $request->headers->set('x-cool-wopi-isexitsave', 'true');

    $this->assertResponse(
      Response::HTTP_OK,
      json_encode([
        'LastModifiedTime' => $mtime->format('c'),
      ]),
     'application/json',
     $request
    );
    $this->assertTrue($this->logger->hasRecord('Save reason: Saved by Collabora Online (Modified by user, Autosaved, Save on Exit)'), 'error');
  }

  /**
   * Tests a request with conflicting timestamp.
   */
  public function testConflictFile(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(write: TRUE),
    ]);

    // Set a time in the future to force the error.
    $mtime = date_create_immutable_from_format('U', (string) ($this->file->getChangedTime() + 1000));
    $request->headers->set('x-cool-wopi-timestamp', $mtime->format(\DateTimeInterface::ATOM));

    $this->assertResponse(
      Response::HTTP_CONFLICT,
      json_encode([
        'COOLStatusCode' => 1010,
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
      'access_token' => $this->getAccessToken(write: TRUE),
    ]);

    $this->assertAccessDeniedResponse($request);
  }

  /**
   * Tests a request using bad UID in payload.
   */
  public function testAccessDeniedUser(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'access_token' => $this->getAccessToken(write: TRUE, uid: 5),
    ]);

    $this->assertAccessDeniedResponse($request);
  }

  /**
   * {@inheritDoc}
   */
  protected function getRequestUri(): string {
    return '/cool/wopi/files/' . $this->media->id() . '/contents';
  }

}
