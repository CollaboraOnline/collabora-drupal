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

namespace Drupal\Tests\collabora_online\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Test for Collabora Online editors embedded in or accessed from Drupal.
 *
 * To make this a regular FunctionalJavascript test, the WOPI request from
 * Collabora to Drupal would need to send the SIMPLETEST_USER_AGENT cookie that
 * tells Drupal that the request should be handled by the test installation,
 * rather than the regular/existing installation.
 *
 * @see \drupal_valid_test_ua()
 */
class CollaboraIntegrationTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Tests the Collabora editor in readonly mode.
   */
  public function testCollaboraPreview(): void {
    $user = $this->createUser([
      'preview document in collabora',
    ]);
    $this->drupalLogin($user);
    $media = $this->createDocumentMedia('Shopping list', 'shopping-list', 'Chocolate, pickles');
    $this->drupalGet('/cool/view/' . $media->id());
    $this->getSession()->switchToIFrame('collabora-online-viewer');

    $assert_session = $this->assertSession();
    $canvas = $assert_session->waitForElement('css', 'canvas#document-canvas');
    $this->assertNotNull($canvas, 'The canvas element was not found after 10 seconds.');

    $document_field = $assert_session->waitForElement('css', 'input#document-name-input');
    $this->assertNotNull($document_field, 'The document name input was not found after 10 seconds.');
    $this->getCurrentPage()->waitFor(10, function () use ($document_field) {
      return $document_field->getValue() === 'shopping-list.txt';
    });
    $this->assertEquals('shopping-list.txt', $document_field->getValue(), 'The document name input did not contain the correct value after 10 seconds.');

    $word_count_element = $assert_session->waitForElement('css', 'div#StateWordCount');
    $this->assertNotNull($word_count_element, 'The word count element was not found after 10 seconds.');
    $this->getCurrentPage()->waitFor(10, function () use ($word_count_element) {
      return $word_count_element->getText() === '2 words, 18 characters';
    });
    $this->assertEquals('2 words, 18 characters', $word_count_element->getText(), 'The word count element did not contain the correct text after 10 seconds.');
  }

  /**
   * Creates a media entity with an attached *.txt file.
   *
   * The *.txt format is enough to test the basic functionality.
   *
   * @param string $media_name
   *   Media label.
   * @param string $file_basename
   *   File name without the extension.
   * @param string $text_content
   *   Content for the attached *.txt file.
   *
   * @return \Drupal\media\MediaInterface
   *   New media entity.
   */
  protected function createDocumentMedia(string $media_name, string $file_basename, string $text_content): MediaInterface {
    $file_uri = 'public://' . $file_basename . '.txt';
    file_put_contents($file_uri, $text_content);
    $file = File::create([
      'uri' => $file_uri,
    ]);
    $file->save();
    $this->markEntityForCleanup($file);
    $values = [
      'bundle' => 'document',
      'name' => $media_name,
      'title' => $media_name,
      'label' => $media_name,
      'field_media_file' => $file->id(),
    ];
    $media = Media::create($values);
    $media->save();
    $this->markEntityForCleanup($media);
    return $media;
  }

}
