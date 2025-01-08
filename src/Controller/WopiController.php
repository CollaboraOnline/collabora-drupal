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

use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\collabora_online\MediaHelperInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides WOPI route responses for the Collabora module.
 */
class WopiController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly JwtTranscoderInterface $jwtTranscoder,
    protected readonly AccountSwitcherInterface $accountSwitcher,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly TimeInterface $time,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly MediaHelperInterface $mediaHelper,
    #[Autowire('logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
    TranslationInterface $string_translation,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * Handles the WOPI 'info' request for a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
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
  public function wopiCheckFileInfo(MediaInterface $media, FileInterface $file, UserInterface $user, bool $can_write): Response {
    $mtime = date_create_immutable_from_format('U', $file->getChangedTime());

    if ($can_write && !$media->access('edit in collabora', $user)) {
      $this->logger->error('Token and user permissions do not match.');
      throw new AccessDeniedHttpException('The user does not have collabora edit access for this media.');
    }

    $response_data = [
      'BaseFileName' => $file->getFilename(),
      'Size' => $file->getSize(),
      'LastModifiedTime' => $mtime->format('c'),
      'UserId' => $user->id(),
      'UserFriendlyName' => $user->getDisplayName(),
      'UserCanWrite' => $can_write,
      'IsAdminUser' => $user->hasPermission('administer collabora instance'),
      'IsAnonymousUser' => $user->isAnonymous(),
    ];

    $user_picture = $user->user_picture?->entity;
    if ($user_picture) {
      $response_data['UserExtraInfo']['avatar'] = $this->fileUrlGenerator->generateAbsoluteString($user_picture->getFileUri());
    }

    $jsonPayload = json_encode($response_data);

    $response = new Response(
      $jsonPayload,
      Response::HTTP_OK,
      ['content-type' => 'application/json'],
    );
    return $response;
  }

  /**
   * Handles the wopi "content" request for a media entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   File attached to the media entity.
   * @param \Drupal\user\UserInterface $user
   *   User entity from the uid in the JWT payload.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response with file contents.
   */
  public function wopiGetFile(FileInterface $file, UserInterface $user): Response {
    $this->accountSwitcher->switchTo($user);

    $response = new BinaryFileResponse(
      $file->getFileUri(),
      Response::HTTP_OK,
      ['content-type' => $file->getMimeType()],
    );
    $this->accountSwitcher->switchBack();
    return $response;
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
  public function wopiPutFile(MediaInterface $media, FileInterface $file, UserInterface $user, bool $can_write, Request $request): Response {
    $timestamp = $request->headers->get('x-cool-wopi-timestamp');

    if (!$can_write) {
      throw new AccessDeniedHttpException('The token only grants read access.');
    }

    $this->accountSwitcher->switchTo($user);

    if ($timestamp) {
      $wopi_stamp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp);
      $file_stamp = \DateTimeImmutable::createFromFormat('U', $file->getChangedTime());

      if ($wopi_stamp != $file_stamp) {
        $this->logger->error('Conflict saving file ' . $media->id() . ' wopi: ' . $wopi_stamp->format('c') . ' differs from file: ' . $file_stamp->format('c'));

        return new Response(
          json_encode(['COOLStatusCode' => 1010]),
          Response::HTTP_CONFLICT,
          ['content-type' => 'application/json'],
        );
      }
    }

    $dir = $this->fileSystem->dirname($file->getFileUri());
    $dest = $dir . '/' . $file->getFilename();

    $content = $request->getContent();
    $owner_id = $file->getOwnerId();
    $uri = $this->fileSystem->saveData($content, $dest, FileExists::Rename);

    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->entityTypeManager->getStorage('file')->create(['uri' => $uri]);
    $file->setOwnerId($owner_id);
    if (is_file($dest)) {
      $file->setFilename($this->fileSystem->basename($dest));
    }
    $file->setPermanent();
    $file->setSize(strlen($content));
    $file->save();
    $mtime = date_create_immutable_from_format('U', $file->getChangedTime());

    $this->mediaHelper->setMediaSource($media, $file);
    $media->setRevisionUser($user);
    $media->setRevisionCreationTime($this->time->getRequestTime());

    $save_reason = $this->buildSaveReason($request);
    $this->logger->error('Save reason: ' . $save_reason);
    $media->setRevisionLogMessage($save_reason);
    $media->save();

    $payload = json_encode([
      'LastModifiedTime' => $mtime->format('c'),
    ]);

    $response = new Response(
      $payload,
      Response::HTTP_OK,
      ['content-type' => 'application/json'],
    );

    $this->accountSwitcher->switchBack();
    return $response;
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
    $modified_by_user = $request->headers->get('x-cool-wopi-ismodifiedbyuser') == 'true';
    $autosave = $request->headers->get('x-cool-wopi-isautosave') == 'true';
    $exitsave = $request->headers->get('x-cool-wopi-isexitsave') == 'true';
    if ($modified_by_user) {
      $reasons[] = 'Modified by user';
    }
    if ($autosave) {
      $reasons[] = 'Autosaved';
    }
    if ($exitsave) {
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

    switch ($action) {
      case 'info':
        return $this->wopiCheckFileInfo($media, $file, $user, $can_write);

      case 'content':
        return $this->wopiGetFile($file, $user);

      case 'save':
        return $this->wopiPutFile($media, $file, $user, $can_write, $request);
    }

    $response = new Response(
      'Invalid WOPI action ' . $action,
      Response::HTTP_BAD_REQUEST,
      ['content-type' => 'text/plain'],
    );
    return $response;
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
    catch (CollaboraNotAvailableException $e) {
      $this->logger->warning('A token cannot be decoded: @message', ['@mesage' => $e->getMessage()]);
      throw new AccessDeniedHttpException('Malformed token');
    }
    if ($values === NULL) {
      throw new AccessDeniedHttpException('Empty token values');
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
