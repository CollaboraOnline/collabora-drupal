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

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\Tests\collabora_online\Traits\MediaCreationTrait;
use Drupal\views\Views;

/**
 * Tests link fields to preview and edit medias in views.
 */
class ViewsLinkFieldsTest extends CollaboraKernelTestBase {

  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online_test',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'views',
      'collabora_online_test',
    ]);
  }

  /**
   * Tests link fields.
   */
  public function testLinks(): void {
    // User without permissions can't see links.
    $this->doTestLinks(
      [
        'preview' => [FALSE, FALSE, FALSE, FALSE],
        'edit' => [FALSE, FALSE, FALSE, FALSE],
      ],
      $this->createUser([]),
    );
    // User with 'Preview' permission can see preview link.
    $this->doTestLinks(
      [
        'preview' => [TRUE, FALSE, TRUE, FALSE],
        'edit' => [FALSE, FALSE, FALSE, FALSE],
      ],
      $this->createUser([
        'preview document in collabora',
      ]),
    );
    // User with 'Preview own unpublished' permission can see preview link
    // for unpublished entity they own.
    $this->doTestLinks(
      [
        'preview' => [FALSE, FALSE, FALSE, TRUE],
        'edit' => [FALSE, FALSE, FALSE, FALSE],
      ],
      $this->createUser([
        'preview own unpublished document in collabora',
      ]),
    );
    // User with 'Edit any' permission can see edit link.
    $this->doTestLinks(
      [
        'preview' => [FALSE, FALSE, FALSE, FALSE],
        'edit' => [TRUE, TRUE, TRUE, TRUE],
      ],
      $this->createUser([
        'edit any document in collabora',
      ]),
    );
    // User with 'Edit own' permission can see edit link for entities they
    // own.
    $this->doTestLinks(
      [
        'preview' => [FALSE, FALSE, FALSE, FALSE],
        'edit' => [FALSE, FALSE, TRUE, TRUE],
      ],
      $this->createUser([
        'edit own document in collabora',
      ]),
    );
  }

  /**
   * Tests that links behave as expected.
   *
   * @param array $expected_results
   *   An associative array of expected results keyed by operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to be used to run the test.
   */
  protected function doTestLinks(array $expected_results, AccountInterface $account): void {
    $this->setCurrentUser($account);
    // Create medias: cover all combinations of status and ownership.
    $this->createMediaEntity('document', [
      'uid' => $this->createUser(),
    ]);
    $this->createMediaEntity('document', [
      'uid' => $this->createUser(),
      'status' => 0,
    ]);
    $this->createMediaEntity('document', [
      'uid' => $account->id(),
    ]);
    $this->createMediaEntity('document', [
      'uid' => $account->id(),
      'status' => 0,
    ]);

    $view = Views::getView('test_collabora_links');
    $view->preview();

    $info = [
      'preview' => [
        'label' => 'View in Collabora Online',
        'field_id' => 'collabora_preview',
        'route' => 'collabora-online.view',
      ],
      'edit' => [
        'label' => 'Edit in Collabora Online',
        'field_id' => 'collabora_edit',
        'route' => 'collabora-online.edit',
      ],
    ];

    // Check each expected results for every media.
    $i = 0;
    foreach (Media::loadMultiple() as $media) {
      foreach ($expected_results as $operation => $expected_result) {
        $expected_link = '';
        // The operations array contains results for each entity.
        if ($expected_result[$i]) {
          $path = Url::fromRoute($info[$operation]['route'], ['media' => $media->id()])->toString();
          $expected_link = '<a href="' . $path . '">' . $info[$operation]['label'] . '</a>';
        }
        // We check the output: link HTML or empty (access denied).
        $link = $view->style_plugin->getField($i, $info[$operation]['field_id']);
        $this->assertEquals($expected_link, (string) $link);
      }
      $i++;
      // Clean medias as we check results.
      $media->delete();
    }
  }

}
