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
   * Tests a successful response.
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
  }

  /**
   * {@inheritDoc}
   */
  protected function getRequestUri(): string {
    return '/cool/wopi/files/' . $this->media->id() . '/contents';
  }

}
