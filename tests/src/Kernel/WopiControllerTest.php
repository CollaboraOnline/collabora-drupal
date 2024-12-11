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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the WopiController.
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'jwt',
    ]);
  }

  /**
   * Tests collabora-online.wopi.info.
   */
  public function testWopiInfoController(): void {
    $media = $this->createMediaEntity('document');
    /** @var \Drupal\collabora_online\Jwt\JwtTranscoderInterface $transcoder */
    $transcoder = \Drupal::service('Drupal\collabora_online\Jwt\JwtTranscoderInterface');

    $expire_timestamp = gettimeofday(TRUE) + 1000;

    $access_token = $transcoder->encode([
      'fid' => '1',
      'uid' => '2',
      'wri' => FALSE,
      'exp' => $expire_timestamp,
    ], $expire_timestamp);

    $request = Request::create(
      '/cool/wopi/files/1',
      'GET',
      [
        'id' => '1',
        'action' => 'info',
        'access_token' => $access_token,
      ]
    );

    $this->setCurrentUser($this->createUser(['access content']));

    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::getContainer()->get('http_kernel');
    $response = $kernel->handle($request);

    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
  }

}
