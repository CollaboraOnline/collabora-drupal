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

use Behat\Mink\Element\NodeElement;
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
    $this->assertCollaboraDocumentCanvas();
    $this->assertCollaboraDocumentName('shopping-list.txt');
    $this->assertCollaboraWordCountString('2 words, 18 characters');

    // Verify the read-only mode.
    $readonly_indicator = $this->assertWaitForElement('.status-readonly-mode');
    $this->assertSame('Read-only', $readonly_indicator->getText());
  }

  /**
   * Tests the Collabora editor in edit mode.
   */
  public function testCollaboraEdit(): void {
    $user = $this->createUser([
      'edit any document in collabora',
      'administer media',
    ]);
    $this->drupalLogin($user);
    $media = $this->createDocumentMedia(
      'Shopping list',
      'shopping-list',
      'Chocolate, pickles',
      'odt',
    );

    $this->drupalGet('/cool/edit/' . $media->id());

    $this->getSession()->switchToIFrame('collabora-online-viewer');
    $this->assertCollaboraDocumentCanvas();
    $this->assertCollaboraDocumentName('shopping-list.odt');
    $this->assertCollaboraWordCountString('2 words, 18 characters');

    // Verify the edit mode.
    // The button is always present when in edit mode, but it is only
    // visible on a mobile / touch device.
    $this->assertWaitForElement('#mobile-edit-button');

    // Detect if Collabora shows a welcome dialog.
    if ($this->getCurrentPage()->find('css', '[name=iframe-welcome-form]')) {
      // The contents of the welcome dialog are in an iframe.
      $this->getSession()->switchToIFrame('iframe-welcome-form');
      // Click the close button of the welcome dialog.
      // The close button itself has dimensions 0x0 and is regarded as not
      // clickable by Selenium. It contains a ::before pseudo-element with
      // non-zero dimensions which would receive the click.
      // To not have to think about this, just click with js.
      $this->getSession()->executeScript("document.querySelector('div#welcome-close').click();");
      // Switch back to the previous iframe.
      $this->getSession()->switchToIFrame(NULL);
      $this->getSession()->switchToIFrame('collabora-online-viewer');
    }

    // Switch to 'File' menu where 'Rename' button should be.
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('File')->click();
    // Button actually exists, but is not visible.
    $this->assertFalse($assert_session->buttonExists('Rename')->isVisible());
  }

  /**
   * Asserts that the Collabora canvas is present.
   *
   * This is a general heuristic indicating that the editor has loaded.
   */
  protected function assertCollaboraDocumentCanvas(): void {
    $this->assertWaitForElement('canvas#document-canvas');
  }

  /**
   * Asserts the document name displayed at the top of the editor.
   *
   * @param string $expected_name
   *   Expected document name.
   */
  protected function assertCollaboraDocumentName(string $expected_name): void {
    $document_field = $this->assertWaitForElement('input#document-name-input');
    $this->getCurrentPage()->waitFor(10, function () use ($document_field, $expected_name) {
      return $document_field->getValue() === $expected_name;
    });
    $this->assertEquals($expected_name, $document_field->getValue(), 'The document name input did not contain the correct value after 10 seconds.');
  }

  /**
   * Asserts text in the word count element.
   *
   * This is a placeholder for testing the actual document text.
   *
   * @param string $expected_text
   *   Expected text for the word count element.
   */
  protected function assertCollaboraWordCountString(string $expected_text): void {
    $word_count_element = $this->assertWaitForElement('div#StateWordCount');
    $this->getCurrentPage()->waitFor(10, function () use ($word_count_element, $expected_text) {
      return $word_count_element->getText() === $expected_text;
    });
    $this->assertEquals($expected_text, $word_count_element->getText(), 'The word count element did not contain the correct text after 10 seconds.');
  }

  /**
   * Waits for an element, and fails if not found.
   *
   * @param string|array $locator
   *   The locator / selector string.
   * @param string $selector
   *   The selector type.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   Node element that matches the selector.
   */
  protected function assertWaitForElement(string|array $locator, string $selector = 'css'): NodeElement {
    $element = $this->assertSession()->waitForElement($selector, $locator);
    $this->assertNotNull($element, "The '$selector:$locator' element was not found after 10 seconds.");
    return $element;
  }

  /**
   * Creates a media entity with an attached file.
   *
   * @param string $media_name
   *   Media label.
   * @param string $file_basename
   *   File name without the extension.
   * @param string $text_content
   *   Content for the attached file.
   * @param string $file_extension
   *   The extension for the attached file.
   *
   * @return \Drupal\media\MediaInterface
   *   New media entity.
   */
  protected function createDocumentMedia(string $media_name, string $file_basename, string $text_content, string $file_extension = 'txt'): MediaInterface {
    $file_uri = 'public://' . $file_basename . '.' . $file_extension;
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
