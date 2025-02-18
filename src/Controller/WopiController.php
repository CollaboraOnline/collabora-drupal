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

namespace Drupal\collabora_online\Controller;

use Drupal\collabora_online\Exception\CollaboraJwtKeyException;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\collabora_online\MediaHelperInterface;
use Drupal\collabora_online\Util\DateTimeHelper;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides WOPI route responses for the Collabora module.
 */
class WopiController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly JwtTranscoderInterface $jwtTranscoder,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly TimeInterface $time,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly MediaHelperInterface $mediaHelper,
    protected readonly ConfigFactoryInterface $configFactory,
    #[Autowire('logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
    TranslationInterface $string_translation,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * Handles the WOPI 'info' request for a media entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   File attached to the media entity.
   * @param \Drupal\user\UserInterface $user
   *   User entity from the uid in the JWT payload.
   * @param bool $can_write
   *   TRUE if the user has write access to the media.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response with file contents.
   */
  protected function wopiCheckFileInfo(FileInterface $file, UserInterface $user, bool $can_write): Response {
    assert($file->getChangedTime() !== NULL);
    $response_data = [
      'BaseFileName' => $file->getFilename(),
      'Size' => $file->getSize(),
      'LastModifiedTime' => DateTimeHelper::format($file->getChangedTime()),
      'UserId' => $user->id(),
      'UserFriendlyName' => $user->getDisplayName(),
      'UserCanWrite' => $can_write,
      'IsAdminUser' => $user->hasPermission('administer collabora instance'),
      'IsAnonymousUser' => $user->isAnonymous(),
    ];

    // @phpstan-ignore property.notFound
    $user_picture = $user->user_picture?->entity;
    if ($user_picture) {
      $response_data['UserExtraInfo']['avatar'] = $this->fileUrlGenerator->generateAbsoluteString($user_picture->getFileUri());
    }

    return new JsonResponse(
      $response_data,
      Response::HTTP_OK,
      ['content-type' => 'application/json'],
    );
  }

  /**
   * Handles the wopi "content" request for a media entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   File attached to the media entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response with file contents.
   *
   * @see \Drupal\system\FileDownloadController::download()
   */
  protected function wopiGetFile(FileInterface $file): Response {
    assert($file->getFileUri() !== NULL);
    if (!is_file($file->getFileUri())) {
      throw new NotFoundHttpException('The file is missing in the file system.');
    }
    return new BinaryFileResponse(
      $file->getFileUri(),
      Response::HTTP_OK,
      ['content-type' => $file->getMimeType()],
    );
  }

  /**
   * Handles the wopi "save" request for a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   * @param \Drupal\file\FileInterface $file
   *   File attached to the media entity.
   * @param \Drupal\user\UserInterface $user
   *   User entity from the uid in the JWT payload.
   * @param bool $can_write
   *   TRUE if the user has write access to the media.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object with headers, query parameters and payload.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  protected function wopiPutFile(MediaInterface $media, FileInterface $file, UserInterface $user, bool $can_write, Request $request): Response {
    if (!$can_write) {
      throw new AccessDeniedHttpException('The token only grants read access.');
    }

    $conflict_response = $this->checkSaveTimestampConflict($request, $media, $file);
    if ($conflict_response) {
      return $conflict_response;
    }

    $new_file_content = $request->getContent();
    $new_file_interval = $this->configFactory->get('collabora_online.settings')->get('cool.new_file_interval') ?? 0;
    $request_time = $this->time->getRequestTime();
    $save_reason = $this->buildSaveReason($request);

    if (
      $new_file_interval === 0 ||
      $request_time - $file->getCreatedTime() <= $new_file_interval
    ) {
      // Replace file with new content.
      assert($file->getFileUri() !== NULL);
      $this->fileSystem->saveData(
        $new_file_content,
        $file->getFileUri(),
        FileExists::Replace,
      );

      // Entity didn't change but file has been replaced.
      $file->save();

      $this->logger->info(
        'The file contents for media @media_id were overwritten with Collabora.<br>
Save reason: @reason<br>
File: @file_id / @file_uri<br>
User ID: @user_id',
        [
          '@media_id' => $media->id(),
          '@reason' => $save_reason,
          '@file_id' => $file->id(),
          '@file_uri' => $file->getFileUri(),
          '@user_id' => $user->id(),
        ],
      );

      assert($file->getChangedTime() !== NULL);
      return new JsonResponse(
        [
          'LastModifiedTime' => DateTimeHelper::format($file->getChangedTime()),
        ],
        Response::HTTP_OK,
        ['content-type' => 'application/json'],
      );
    }

    $new_file = $this->createNewFileEntity($file, $new_file_content);

    $this->mediaHelper->setMediaSource($media, $new_file);
    $media->setRevisionUser($user);
    $media->setRevisionCreationTime($request_time);
    $media->setRevisionLogMessage($save_reason);
    $media->save();

    $this->logger->info(
      'Media entity @media_id was updated with Collabora.<br>
Save reason: @reason<br>
Old file: @old_file_id / @old_file_uri<br>
New file: @new_file_id / @new_file_uri<br>
User ID: @user_id',
      [
        '@media_id' => $media->id(),
        '@reason' => $save_reason,
        '@old_file_id' => $file->id(),
        '@old_file_uri' => $file->getFileUri(),
        '@new_file_id' => $new_file->id(),
        '@new_file_uri' => $new_file->getFileUri(),
        '@user_id' => $user->id(),
      ],
    );

    assert($new_file->getChangedTime() !== NULL);
    return new JsonResponse(
      [
        'LastModifiedTime' => DateTimeHelper::format($new_file->getChangedTime()),
      ],
      Response::HTTP_OK,
      ['content-type' => 'application/json'],
    );
  }

  /**
   * Creates a new file entity with given file content.
   *
   * @param \Drupal\file\FileInterface $file
   *   Old file entity.
   * @param string $new_file_content
   *   New file content to save.
   *
   * @return \Drupal\file\FileInterface
   *   New file entity.
   *   This may have a different uri, but will have the same filename.
   */
  protected function createNewFileEntity(FileInterface $file, string $new_file_content): FileInterface {
    assert($file->getFileUri() !== NULL);
    // The current file uri may have a number suffix like "_0".
    // For the new file uri, start with the clean file name, to avoid repeated
    // suffixes like "_0_0_0".
    $dir = $this->fileSystem->dirname($file->getFileUri());
    $dest = $dir . '/' . $file->getFilename();

    $new_file_uri = $this->fileSystem->saveData(
      $new_file_content,
      $dest,
      FileExists::Rename,
    );

    /** @var \Drupal\file\FileInterface $new_file */
    $new_file = $this->entityTypeManager->getStorage('file')->create([
      'uri' => $new_file_uri,
    ]);
    // The user id field always exists for files.
    // If no owner is set, it will be 0 or '0', but not NULL.
    assert($file->getOwnerId() !== NULL);
    $new_file->setOwnerId($file->getOwnerId());
    // Preserve the original file name, no matter the uri was renamed.
    $new_file->setFilename($file->getFilename());
    $new_file->setPermanent();
    $new_file->save();

    return $new_file;
  }

  /**
   * Checks for a timestamp conflict on save.
   *
   * A conflicting timestamp indicates that the file was updated outside of the
   * Collabora editing session.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Incoming WOPI save request.
   * @param \Drupal\media\MediaInterface $media
   *   Media entity, only used for logging.
   * @param \Drupal\file\FileInterface $file
   *   File entity attached to the media entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response|null
   *   Failure response, or NULL if no conflict.
   */
  protected function checkSaveTimestampConflict(Request $request, MediaInterface $media, FileInterface $file): ?Response {
    $wopi_time_atom = $request->headers->get('x-cool-wopi-timestamp');
    if (!$wopi_time_atom) {
      return NULL;
    }
    $wopi_datetime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $wopi_time_atom);
    $file_datetime = \DateTimeImmutable::createFromFormat('U', (string) $file->getChangedTime());

    assert($wopi_datetime !== FALSE);
    assert($file_datetime !== FALSE);

    if ($wopi_datetime == $file_datetime) {
      return NULL;
    }

    $this->logger->error(
      'Conflict saving file for media @media_id: WOPI time @wopi_time differs from file time @file_time.',
      [
        '@media_id' => $media->id(),
        '@wopi_time' => $wopi_datetime->format('c'),
        '@file_time' => $file_datetime->format('c'),
      ],
    );

    return new JsonResponse(
      ['COOLStatusCode' => 1010],
      Response::HTTP_CONFLICT,
      ['content-type' => 'application/json'],
    );
  }

  /**
   * Builds a reason string why the file is being saved.
   *
   * This is used for a log message an for the revision log message.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Incoming request.
   *
   * @return string
   *   Reason string.
   */
  protected function buildSaveReason(Request $request): string {
    $save_reason = 'Saved by Collabora Online';
    $reasons = [];
    if ($request->headers->get('x-cool-wopi-ismodifiedbyuser') == 'true') {
      $reasons[] = 'Modified by user';
    }
    if ($request->headers->get('x-cool-wopi-isautosave') == 'true') {
      $reasons[] = 'Autosaved';
    }
    if ($request->headers->get('x-cool-wopi-isexitsave') == 'true') {
      $reasons[] = 'Save on Exit';
    }
    if (count($reasons) > 0) {
      $save_reason .= ' (' . implode(', ', $reasons) . ')';
    }
    return $save_reason;
  }

  /**
   * The WOPI entry point.
   *
   * @param string $action
   *   One of 'info', 'content' or 'save', depending with path is visited.
   * @param \Drupal\media\MediaInterface $media
   *   Media entity from the media id in the URL.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object for headers and query parameters.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response to be consumed by Collabora Online.
   */
  public function wopi(string $action, MediaInterface $media, Request $request): Response {
    $token = $request->get('access_token');
    if ($token === NULL) {
      throw new AccessDeniedHttpException('Missing access token.');
    }
    if (!is_string($token)) {
      // A malformed request could have a non-string value for access_token.
      throw new AccessDeniedHttpException(sprintf('Expected a string access token, found %s.', gettype($token)));
    }
    $jwt_payload = $this->verifyTokenForMedia($token, $media);

    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($jwt_payload['uid']);
    if ($user === NULL) {
      throw new AccessDeniedHttpException('User not found.');
    }

    $file = $this->mediaHelper->getFileForMedia($media);
    if ($file === NULL) {
      throw new AccessDeniedHttpException('No file attached to media.');
    }

    $can_write = !empty($jwt_payload['wri']);

    if ($can_write &&
      $action !== 'content' &&
      !$media->access('edit in collabora', $user)
    ) {
      // The edit permission has been revoked since the token was created.
      throw new AccessDeniedHttpException('The user does not have collabora edit access for this media.');
    }

    switch ($action) {
      case 'info':
        return $this->wopiCheckFileInfo($file, $user, $can_write);

      case 'content':
        return $this->wopiGetFile($file);

      case 'save':
        return $this->wopiPutFile($media, $file, $user, $can_write, $request);
    }

    return new Response(
      'Invalid WOPI action ' . $action,
      Response::HTTP_BAD_REQUEST,
      ['content-type' => 'text/plain'],
    );
  }

  /**
   * Decodes and verifies a JWT token.
   *
   * Verification include:
   *  - matching $id with fid in the payload
   *  - verifying the expiration.
   *
   * @param string $token
   *   The token to verify.
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   *
   * @return array
   *   Data decoded from the token.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   The token is malformed, invalid or has expired.
   */
  protected function verifyTokenForMedia(
    #[\SensitiveParameter]
    string $token,
    MediaInterface $media,
  ): array {
    try {
      $values = $this->jwtTranscoder->decode($token);
    }
    catch (CollaboraJwtKeyException $e) {
      $this->logger->warning('A token cannot be decoded: @message', ['@message' => $e->getMessage()]);
      throw new AccessDeniedHttpException('Token verification is not possible right now.');
    }
    if ($values === NULL) {
      throw new AccessDeniedHttpException('Bad token');
    }
    if ((string) $values['fid'] !== (string) $media->id()) {
      throw new AccessDeniedHttpException(sprintf(
        // The token payload is not encrypted, just encoded.
        // It is ok to reveal its values in the response for logging.
        'Found fid %s in request path, but fid %s in token payload',
        $media->id(),
        $values['fid'],
      ));
    }
    return $values;
  }

}
