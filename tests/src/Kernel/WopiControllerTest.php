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

namespace Drupal\Tests\collabora_online\Kernel;

use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the WopiController.
 *
 *  @see \Drupal\collabora_online\Controller\WopiController
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $collabora_settings = \Drupal::configFactory()->getEditable('collabora_online.settings');
    $cool = $collabora_settings->get('cool');
    $cool['key_id'] = 'collabora';
    $collabora_settings->set('cool', $cool)->save();

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
   * Tests a successful response for check file info.
   *
   * @see \Drupal\collabora_online\Controller\WopiController::wopiCheckFileInfo()
   */
  public function testWopiInfoSuccess(): void {
    $request = Request::create(
      '/cool/wopi/files/' . $this->media->id(),
      'GET',
      [
        'id' => $this->media->id(),
        'action' => 'info',
        'access_token' => $this->getAccessToken(),
      ]
    );

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
      ]),
      $request
    );
  }

  /**
   * Tests a successful response for check file info with write operation.
   *
   * @see \Drupal\collabora_online\Controller\WopiController::wopiCheckFileInfo()
   */
  public function testWopiInfoSuccessWrite(): void {
    $request = Request::create(
      '/cool/wopi/files/' . $this->media->id(),
      'GET',
      [
        'id' => $this->media->id(),
        'action' => 'info',
        'access_token' => $this->getAccessToken(write: TRUE),
      ]
    );

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
      $request
    );
  }

  /**
   * Tests response for check file info with a bad token.
   *
   * @see \Drupal\collabora_online\Controller\WopiController::wopiCheckFileInfo()
   */
  public function testWopiInfoAccessDeniedToken(): void {
    $request = Request::create(
      '/cool/wopi/files/' . $this->media->id(),
      'GET',
      [
        'id' => $this->media->id(),
        'action' => 'info',
        'access_token' => 'a',
      ]
    );

    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      'Authentication failed.',
      $request
    );
  }

  /**
   * Tests response for check file info with a bad MID.
   *
   * @see \Drupal\collabora_online\Controller\WopiController::wopiCheckFileInfo()
   */
  public function testWopiInfoAccessDeniedMedia(): void {
    $this->assertNull(Media::load(5));

    $request = Request::create(
      '/cool/wopi/files/5',
      'GET',
      [
        'id' => '5',
        'action' => 'info',
        'access_token' => $this->getAccessToken(),
      ]
    );

    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      'Authentication failed.',
      $request
    );
  }

  /**
   * Tests response for check file info with a bad UID.
   *
   * @see \Drupal\collabora_online\Controller\WopiController::wopiCheckFileInfo()
   */
  public function testWopiInfoAccessDeniedUser(): void {
    $this->assertNull(User::load(5));

    $request = Request::create(
      '/cool/wopi/files/' . $this->media->id(),
      'GET',
      [
        'id' => $this->media->id(),
        'action' => 'info',
        'access_token' => $this->getAccessToken(uid: 5),
      ]
    );

    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      'Authentication failed.',
      $request
    );
  }

  /**
   * Tests response for check file info without permission to edit.
   *
   * @see \Drupal\collabora_online\Controller\WopiController::wopiCheckFileInfo()
   */
  public function testWopiInfoAccessDeniedWrite(): void {
    $this->user = $this->createUser(['access content']);
    $this->setCurrentUser($this->user);

    $request = Request::create(
      '/cool/wopi/files/' . $this->media->id(),
      'GET',
      [
        'id' => $this->media->id(),
        'action' => 'info',
        'access_token' => $this->getAccessToken(write: TRUE),
      ]
    );

    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      'Authentication failed.',
      $request
    );
  }

  /**
   * Retrieves an encoded access token.
   *
   * @param int $fid
   *   The file id.
   * @param int $uid
   *   The user id.
   * @param bool $write
   *   The write permission.
   *
   * @return string
   *   The enconded token.
   */
  protected function getAccessToken(?int $fid = NULL, ?int $uid = NULL, bool $write = FALSE): string {
    /** @var \Drupal\collabora_online\Jwt\JwtTranscoderInterface $transcoder */
    $transcoder = \Drupal::service(JwtTranscoderInterface::class);
    $expire_timestamp = gettimeofday(TRUE) + 1000;

    return $transcoder->encode([
      'fid' => $fid ?? $this->file->id(),
      'uid' => $uid ?? $this->user->id(),
      'wri' => $write,
      'exp' => $expire_timestamp,
    ], $expire_timestamp);
  }

  /**
   * Asserts status code and content from a request.
   */
  protected function assertResponse(int $expected_code, string $expected_content, Request $request): void {
    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::service('http_kernel');
    $response = $kernel->handle($request);

    $this->assertEquals($expected_code, $response->getStatusCode());
    $this->assertEquals($expected_content, $response->getContent());
  }

}
