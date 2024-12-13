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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the WopiController content.
 *
 * @see \Drupal\collabora_online\Controller\WopiController::wopiGetFile()
 */
class WopiControllerContentTest extends WopiControllerTestBase {

  /**
   * Tests a request with successful response.
   */
  public function testSuccess(): void {
    $request = $this->createRequest([
      'id' => $this->media->id(),
      'action' => 'content',
      'access_token' => $this->getAccessToken(),
    ]);

    $this->assertResponse(
     Response::HTTP_OK,
     $this->file->getFileUri(),
     $this->file->getMimeType(),
     $request
    );
  }

  /**
   * {@inheritDoc}
   */
  protected function getRequestUri(): string {
    return '/cool/wopi/files/' . $this->media->id() . '/contents';
  }

  /**
   * {@inheritDoc}
   */
  protected function assertResponse(int $expected_code, string $expected_content, string $expected_content_type, Request $request): void {
    if ($expected_code !== Response::HTTP_OK) {
      parent::assertResponse($expected_code, $expected_content, $expected_content_type, $request);
      return;
    }

    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::service('http_kernel');
    $response = $kernel->handle($request);

    $this->assertEquals($expected_code, $response->getStatusCode());
    $this->assertEquals($expected_content, $response->getFile());
    $this->assertEquals($expected_content_type, $response->headers->get('Content-Type'));
  }

}
