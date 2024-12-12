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
use Drupal\media\MediaInterface;
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
    'jwt',
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

    $this->installConfig([
      'jwt',
    ]);

    $this->user = $this->createUser(['access content']);
    $this->media = $this->createMediaEntity('document');
    $fid = $this->media->getSource()->getSourceFieldValue($this->media);
    $this->file = File::load($fid);

    $this->setCurrentUser($this->user);
  }

  /**
   * Tests sucessful response for check file info.
   *
   * @see \Drupal\collabora_online\Controller\WopiController::wopiCheckFileInfo()
   */
  public function testWopiInfoSucess(): void {
    /** @var \Drupal\collabora_online\Jwt\JwtTranscoderInterface $transcoder */
    $transcoder = \Drupal::service(JwtTranscoderInterface::class);
    $expire_timestamp = gettimeofday(TRUE) + 1000;

    $access_token = $transcoder->encode([
      'fid' => $this->file->id(),
      'uid' => $this->user->id(),
      'wri' => FALSE,
      'exp' => $expire_timestamp,
    ], $expire_timestamp);

    $request = Request::create(
      '/cool/wopi/files/' . $this->media->id(),
      'GET',
      [
        'id' => $this->media->id(),
        'action' => 'info',
        'access_token' => $access_token,
      ]
    );

    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::service('http_kernel');
    $response = $kernel->handle($request);
    $content = json_decode($response->getContent(), TRUE);
    $mtime = date_create_immutable_from_format('U', (string) $this->file->getChangedTime());

    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertEquals([
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
    ], $content);
  }

}
