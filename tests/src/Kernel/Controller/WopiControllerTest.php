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

use Drupal\collabora_online\Util\DateTimeHelper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\media\Entity\Media;
use Drupal\Tests\collabora_online\Traits\UserPictureTrait;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\collabora_online\Controller\WopiController
 */
class WopiControllerTest extends WopiControllerTestBase {

  use UserPictureTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime_testing',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createUserPictureField();
    // Reload the user after the new field is created.
    $this->user = User::load($this->user->id());

    // Disable the WOPI proof for this test.
    $this->config('collabora_online.settings')
      ->set('cool.wopi_proof', FALSE)
      ->save();
  }

  /**
   * Tests successful requests to the 'collabora-online.wopi.info' route.
   *
   * @covers ::wopiCheckFileInfo
   */
  public function testWopiGetFileInfo(): void {
    $file_changed_time = \DateTimeImmutable::createFromFormat('U', (string) $this->file->getChangedTime());
    $expected_response_data = [
      'BaseFileName' => $this->file->getFilename(),
      'Size' => $this->file->getSize(),
      'LastModifiedTime' => $file_changed_time->format('c'),
      'UserId' => $this->user->id(),
      'UserFriendlyName' => $this->user->getDisplayName(),
      'UserCanWrite' => FALSE,
      'IsAdminUser' => FALSE,
      'IsAnonymousUser' => FALSE,
    ];

    $request = $this->createRequest();
    $this->assertJsonResponseOk($expected_response_data, $request);

    $request = $this->createRequest(write: TRUE);
    $expected_response_data['UserCanWrite'] = TRUE;
    $this->assertJsonResponseOk($expected_response_data, $request);

    // Create a user picture, and attach it to the user.
    $user_picture_file = $this->createUserPicture($this->user);

    $expected_response_data['UserCanWrite'] = FALSE;
    /** @var \Drupal\Core\File\FileSystemInterface $filesystem */
    $filesystem = \Drupal::service(FileSystemInterface::class);
    $user_picture_realpath = $filesystem->realpath($user_picture_file->getFileUri());
    // The user picture url looks a bit odd in a kernel test.
    $this->assertMatchesRegularExpression('#^vfs\://root/sites/simpletest/\d+/files/user\-picture\.png$#', $user_picture_realpath);
    $expected_response_data['UserExtraInfo']['avatar'] = 'http://localhost/' . $user_picture_realpath;

    $request = $this->createRequest();
    $this->assertJsonResponseOk($expected_response_data, $request);
  }

  /**
   * Tests successful requests to the 'collabora-online.wopi.contents' route.
   *
   * @covers ::wopiGetFile
   */
  public function testWopiGetFile(): void {
    $request = $this->createRequest(
      '/contents',
    );
    $response = $this->handleRequest($request);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertEquals($this->file->getFileUri(), $response->getFile());
    $this->assertEquals($this->file->getMimeType(), $response->headers->get('Content-Type'));
  }

  /**
   * Tests successful requests to the 'collabora-online.wopi.save' route.
   *
   * @covers ::wopiPutFile
   */
  public function testWopiPutFile(): void {
    $file_changed_time = \DateTimeImmutable::createFromFormat('U', (string) $this->file->getChangedTime());

    // Test a successful save request without timestamp header.
    $this->doTestWopiPutFile();

    // Test a successful save request with a timestamp header.
    $headers = [];
    $headers['x-cool-wopi-timestamp'] = $file_changed_time->format(\DateTimeInterface::ATOM);
    $this->doTestWopiPutFile($headers);

    // Test how different headers result in different log messages.
    $headers['x-cool-wopi-ismodifiedbyuser'] = 'true';
    $this->doTestWopiPutFile(
      $headers,
      'Saved by Collabora Online (Modified by user)',
    );

    $headers['x-cool-wopi-isautosave'] = 'true';
    $this->doTestWopiPutFile(
      $headers,
      'Saved by Collabora Online (Modified by user, Autosaved)',
    );

    $headers['x-cool-wopi-isexitsave'] = 'true';
    $this->doTestWopiPutFile(
      $headers,
      'Saved by Collabora Online (Modified by user, Autosaved, Save on Exit)',
    );
  }

  /**
   * Tests copy frequency on successful requests.
   *
   * @covers ::wopiPutFile
   */
  public function testWopiPutFileCopyFrequency(): void {
    // Change configuration to 300 seconds and attempt a save immediately after.
    // File was created just before, so file copy won't be triggered.
    $wopi_settings = \Drupal::configFactory()->getEditable('collabora_online.settings');
    $wopi_settings->set('cool.copy_file_frequency', 300)->save();
    $this->doTestWopiPutFile();

    // Time passes but it's still under configured frequency.
    \Drupal::time()->setTime('+100 seconds');
    $this->doTestWopiPutFile();

    // Wait more than 300 seconds to trigger file copy.
    \Drupal::time()->setTime('+301 seconds');
    $this->doTestWopiPutFile(new_file: TRUE);

    // Multiple sequential calls under the configured time prevents duplication.
    \Drupal::time()->setTime('+295 seconds');
    $this->doTestWopiPutFile();
    \Drupal::time()->setTime('+295 seconds');
    $this->doTestWopiPutFile();
    \Drupal::time()->setTime('+295 seconds');
    $this->doTestWopiPutFile();

    // Configured frequency of 0 won't copy files no matter how much we wait.
    $wopi_settings = \Drupal::configFactory()->getEditable('collabora_online.settings');
    $wopi_settings->set('cool.copy_file_frequency', 0)->save();
    $this->doTestWopiPutFile();
    \Drupal::time()->setTime('+1000 seconds');
    $this->doTestWopiPutFile();
  }

  /**
   * Does test the 'collabora-online.wopi.save' route with specific parameters.
   *
   * This is called repeatedly from the same test method, to save time.
   *
   * @param array<string, string> $request_headers
   *   Request headers.
   * @param string $reason_message
   *   Reason message expected to appear in the log and in the revision log.
   * @param string $new_file
   *   New file is expected.
   */
  protected function doTestWopiPutFile(
    array $request_headers = [],
    string $reason_message = 'Saved by Collabora Online',
    bool $new_file = FALSE,
  ): void {
    $new_file_content = "File content " . str_repeat('m', rand(0, 999)) . '.';
    $old_file = $this->loadCurrentMediaFile();
    $this->logger->reset();
    $request = $this->createRequest(
      '/contents',
      'POST',
      write: TRUE,
      content: $new_file_content,
    );
    $request->headers->add($request_headers);

    $request_time = \Drupal::time()->getRequestTime();
    $this->assertJsonResponseOk(
      [
        'LastModifiedTime' => DateTimeHelper::format($request_time),
      ],
      $request,
    );

    $media = Media::load($this->media->id());
    $file = $this->loadCurrentMediaFile();

    // File entity and content are updated.
    $this->assertSame($request_time, $file->getChangedTime());
    $actual_file_content = file_get_contents($file->getFileUri());
    $this->assertSame($new_file_content, $actual_file_content);
    $this->assertSame(strlen($new_file_content), $file->getSize());

    if (!$new_file) {
      // The URI remains the same, and no File entity has been created.
      $this->assertSame($old_file->id(), $file->id());
      $this->assertSame($file->getFileUri(), $file->getFileUri());
      $this->assertNoFurtherLogMessages();

      return;
    }

    $i = $this->getCounterValue();
    // Assert that a new file was created.
    $this->assertGreaterThan((int) $old_file->id(), (int) $file->id());
    // The file uri is fully predictable in the context of this test.
    // Each new file version gets a new number suffix.
    // There is no repeated suffix like "test_0_0_0_0.txt".
    $this->assertSame('public://test_' . $i . '.txt', $file->getFileUri());
    // The file name is preserved.
    $this->assertSame('test.txt', $file->getFilename());
    // The file owner is preserved.
    $this->assertSame($this->fileOwner->id(), $file->getOwnerId());
    $this->assertTrue($file->isPermanent());

    // Revision messages and logs.
    $this->assertSame($reason_message, $media->getRevisionLogMessage());
    $this->assertLogMessage(
      RfcLogLevel::INFO,
      'Media entity @media_id was updated with Collabora.<br>
Save reason: @reason<br>
Old file: @old_file_id / @old_file_uri<br>
New file: @new_file_id / @new_file_uri',
      [
        '@media_id' => $this->media->id(),
        '@reason' => $reason_message,
        '@old_file_id' => $old_file->id(),
        '@old_file_uri' => $old_file->getFileUri(),
        '@new_file_id' => $file->id(),
        '@new_file_uri' => $file->getFileUri(),
      ],
    );
  }

  /**
   * Gets an integer value that is different for each call.
   *
   * @return int
   *   Unique integer value, starting at zero.
   */
  protected function getCounterValue(): int {
    static $i = 0;
    return $i++;
  }

  /**
   * Tests the 'collabora-online.wopi.save' route without write access.
   *
   * @covers ::wopiPutFile
   */
  public function testWopiPutFileReadOnly(): void {
    $request = $this->createRequest('/contents', 'POST', write: FALSE);
    $this->assertAccessDeniedResponse(
      'The token only grants read access.',
      $request,
    );
  }

  /**
   * Tests the 'collabora-online.wopi.save' route with a conflicting timestamp.
   *
   * @covers ::wopiPutFile
   */
  public function testWopiPutFileConflict(): void {
    $request = $this->createRequest('/contents', 'POST', write: TRUE);

    $file_changed_time = \DateTimeImmutable::createFromFormat('U', (string) ($this->file->getChangedTime()));
    // Set a time in the future to force the error.
    $wopi_changed_time = \DateTimeImmutable::createFromFormat('U', (string) ($this->file->getChangedTime() + 1000));
    $request->headers->set('x-cool-wopi-timestamp', $wopi_changed_time->format(\DateTimeInterface::ATOM));

    $this->assertJsonResponse(
      Response::HTTP_CONFLICT,
      [
        'COOLStatusCode' => 1010,
      ],
      $request,
    );
    $this->assertLogMessage(
      RfcLogLevel::ERROR,
      'Conflict saving file for media @media_id: WOPI time @wopi_time differs from file time @file_time.',
      [
        '@media_id' => $this->media->id(),
        '@wopi_time' => $wopi_changed_time->format('c'),
        '@file_time' => $file_changed_time->format('c'),
      ],
    );
  }

  /**
   * Tests different routes without the token.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testMissingToken(): void {
    $requests = $this->createRequests();
    foreach ($requests as $name => $request) {
      $request->request->remove('access_token');
      $request->query->remove('access_token');
      $this->assertAccessDeniedResponse(
        'Missing access token.',
        $request,
        $name,
      );
    }
  }

  /**
   * Tests different routes using an invalid token.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testBadToken(): void {
    $requests = $this->createRequests();
    foreach ($requests as $name => $request) {
      // Replace the token with a value that is not in the JWT format.
      $request->query->set('access_token', 'bad_token');
      $this->assertAccessDeniedResponse(
        'Bad token',
        $request,
        $name,
      );
      $this->assertLogMessage(
        RfcLogLevel::ERROR,
        'Wrong number of segments',
        assertion_message: $name,
      );
      $this->assertNoFurtherLogMessages($name);
    }
  }

  /**
   * Tests different routes using an invalid token.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testConfigJwtKeyNameEmpty(): void {
    $requests = $this->createRequests();
    $this->config('collabora_online.settings')->set('cool.key_id', '')->save();
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse(
        'Token verification is not possible right now.',
        $request,
        $name,
      );
      $this->assertLogMessage(
        RfcLogLevel::WARNING,
        'A token cannot be decoded: @message',
        [
          '@message' => 'No key was chosen for use in Collabora.',
        ],
        assertion_message: $name,
      );
    }
  }

  /**
   * Tests different routes using an invalid token.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testConfigJwtKeyMissing(): void {
    $requests = $this->createRequests();
    $this->config('collabora_online.settings')->set('cool.key_id', '_unknown_key_')->save();
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse(
        'Token verification is not possible right now.',
        $request,
        $name,
      );
      $this->assertLogMessage(
        RfcLogLevel::WARNING,
        'A token cannot be decoded: @message',
        [
          '@message' => 'The key with id \'_unknown_key_\' is empty or does not exist.',
        ],
        assertion_message: $name,
      );
    }
  }

  /**
   * Tests different routes using the wrong token payload values.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testWrongTokenPayload(): void {
    // Inject a bad value into the token payload.
    $requests = $this->createRequests(token_payload: ['fid' => 4321]);
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse(
        sprintf('Found fid %s in request path, but fid 4321 in token payload', $this->media->id()),
        $request,
        $name,
      );
    }
  }

  /**
   * Tests different routes using a non-existing media id.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testMediaNotFound(): void {
    $requests = $this->createRequests(media_id: 555);
    foreach ($requests as $name => $request) {
      $this->assertNotFoundResponse(
        'Media not found.',
        $request,
        $name,
      );
    }
  }

  /**
   * Tests different routes when the file entity is missing.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testMissingFileEntity(): void {
    $this->file->delete();
    $requests = $this->createRequests();
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse(
        'No file attached to media.',
        $request,
        $name,
      );
    }
  }

  /**
   * Tests different routes when the file is missing in the filesystem.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testFileMissingInFilesystem(): void {
    $uri = $this->file->getFileUri();
    $this->assertFileExists($uri);
    unlink($this->file->getFileUri());
    $this->assertFileDoesNotExist($uri);
    $requests = $this->createRequests();
    foreach ($requests as $name => $request) {
      if ($name === 'file') {
        $this->assertNotFoundResponse(
          'The file is missing in the file system.',
          $request,
          $name,
        );
      }
      else {
        // Currently, the info and save routes still work if the original file
        // is missing in the file system.
        $response = $this->handleRequest($request);
        $this->assertSame(200, $response->getStatusCode(), $name);
      }
    }
  }

  /**
   * Tests different routes using a non-existing user id.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testUserNotFound(): void {
    $requests = $this->createRequests(user_id: 555);
    unset($requests['file']);
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse(
        'User not found.',
        $request,
        $name,
      );
    }
  }

  /**
   * Tests routes with 'wri' in the token but the user has no write permission.
   *
   * @covers ::wopiCheckFileInfo
   * @covers ::wopiGetFile
   * @covers ::wopiPutFile
   */
  public function testNoWritePermission(): void {
    $this->user = $this->createUser(['access content']);
    $this->setCurrentUser($this->user);
    $requests = [
      'info' => $this->createRequest(write: TRUE),
      'save' => $this->createRequest(
        '/contents',
        'POST',
        write: TRUE,
        content: 'xyz',
      ),
    ];
    foreach ($requests as $name => $request) {
      $this->assertAccessDeniedResponse(
        'The user does not have collabora edit access for this media.',
        $request,
        $name,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function assertPostConditions(): void {
    parent::assertPostConditions();
    $this->assertNoFurtherLogMessages();
  }

}
