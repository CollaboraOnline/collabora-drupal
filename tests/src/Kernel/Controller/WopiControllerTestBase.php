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
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\collabora_online\Kernel\CollaboraKernelTestBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class to test controller responses in Collabora.
 */
abstract class WopiControllerTestBase extends CollaboraKernelTestBase {

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
  protected UserInterface|string $user;

  /**
   * The media where to perform operations.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected MediaInterface|string $media;

  /**
   * The source file.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface|string $file;

  /**
   * The test logger channel.
   *
   * @var \ColinODell\PsrTestLogger\TestLogger
   */
  protected TestLogger $logger;

  /**
   * Method used for requests.
   *
   * @var string
   */
  const METHOD = 'GET';

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

    return $transcoder->encode(
      [
        'fid' => $fid ?? $this->file->id(),
        'uid' => $uid ?? $this->user->id(),
        'wri' => $write,
        'exp' => $expire_timestamp,
      ],
      $expire_timestamp
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
   */
  protected function assertResponse(int $expected_code, string $expected_content, string $expected_content_type, Request $request): void {
    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::service('http_kernel');
    $response = $kernel->handle($request);

    $this->assertEquals($expected_code, $response->getStatusCode());
    $this->assertEquals($expected_content, $response->getContent());
    $this->assertEquals($expected_content_type, $response->headers->get('Content-Type'));
  }

  /**
   * Creates a request.
   *
   * @param array $params
   *   The parameters sent to the request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createRequest(array $params): Request {
    return Request::create(
      $this->getRequestUri(),
      self::METHOD,
      $params
    );
  }

  /**
   * Retrieves URI used for requests.
   *
   * @return string
   *   The URI.
   */
  abstract protected function getRequestUri() :string;

}
