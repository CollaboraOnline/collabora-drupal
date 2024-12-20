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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
   * Creates a failure response that is understood by Collabora.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object.
   */
  public static function permissionDenied(): Response {
    return new Response(
      'Authentication failed.',
      Response::HTTP_FORBIDDEN,
      ['content-type' => 'text/plain'],
    );
  }

  /**
   * Handles the WOPI 'info' request for a media entity.
   *
   * @param string $id
   *   Media id from url.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object with query parameters.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response with file contents.
   */
  public function wopiCheckFileInfo(string $id, Request $request): Response {
    $token = $request->query->get('access_token');

    $jwt_payload = $this->verifyTokenForMediaId($token, $id);
    if ($jwt_payload === NULL) {
      return static::permissionDenied();
    }

    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $this->entityTypeManager->getStorage('media')->load($id);
    if ($media === NULL) {
      return static::permissionDenied();
    }

    $file = $this->mediaHelper->getFileForMedia($media);
    if ($file === NULL) {
      return static::permissionDenied();
    }

    $mtime = date_create_immutable_from_format('U', $file->getChangedTime());
    // @todo What if the uid in the payload is not set?
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($jwt_payload['uid']);
    if ($user === NULL) {
      return static::permissionDenied();
    }
    $can_write = $jwt_payload['wri'];

    if ($can_write && !$media->access('edit in collabora', $user)) {
      $this->logger->error('Token and user permissions do not match.');
      return static::permissionDenied();
    }

    $payload = [
      'BaseFileName' => $file->getFilename(),
      'Size' => $file->getSize(),
      'LastModifiedTime' => $mtime->format('c'),
      'UserId' => $jwt_payload['uid'],
      'UserFriendlyName' => $user->getDisplayName(),
      'UserCanWrite' => $can_write,
      'IsAdminUser' => $user->hasPermission('administer collabora instance'),
      'IsAnonymousUser' => $user->isAnonymous(),
    ];

    $user_picture = $user->user_picture?->entity;
    if ($user_picture) {
      $payload['UserExtraInfo']['avatar'] = $this->fileUrlGenerator->generateAbsoluteString($user_picture->getFileUri());
    }

    $jsonPayload = json_encode($payload);

    $response = new Response(
      $jsonPayload,
      Response::HTTP_OK,
      ['content-type' => 'application/json']
    );
    return $response;
  }

  /**
   * Handles the wopi "content" request for a media entity.
   *
   * @param string $id
   *   Media id from url.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object with query parameters.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response with file contents.
   */
  public function wopiGetFile(string $id, Request $request): Response {
    $token = $request->query->get('access_token');

    $jwt_payload = $this->verifyTokenForMediaId($token, $id);
    if ($jwt_payload === NULL) {
      return static::permissionDenied();
    }

    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($jwt_payload['uid']);
    $this->accountSwitcher->switchTo($user);

    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $this->entityTypeManager->getStorage('media')->load($id);
    if ($media === NULL) {
      return static::permissionDenied();
    }

    $file = $this->mediaHelper->getFileForMedia($media);
    if ($file === NULL) {
      return static::permissionDenied();
    }
    $mimetype = $file->getMimeType();

    $response = new BinaryFileResponse(
      $file->getFileUri(),
      Response::HTTP_OK,
      ['content-type' => $mimetype]
    );
    $this->accountSwitcher->switchBack();
    return $response;
  }

  /**
   * Handles the wopi "save" request for a media entity.
   *
   * @param string $id
   *   Media id from url.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object with headers, query parameters and payload.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function wopiPutFile(string $id, Request $request): Response {
    $token = $request->get('access_token');
    $timestamp = $request->headers->get('x-cool-wopi-timestamp');
    $modified_by_user = $request->headers->get('x-cool-wopi-ismodifiedbyuser') == 'true';
    $autosave = $request->headers->get('x-cool-wopi-isautosave') == 'true';
    $exitsave = $request->headers->get('x-cool-wopi-isexitsave') == 'true';

    $jwt_payload = $this->verifyTokenForMediaId($token, $id);
    if ($jwt_payload == NULL || empty($jwt_payload['wri'])) {
      return static::permissionDenied();
    }

    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $this->entityTypeManager->getStorage('media')->load($id);
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($jwt_payload['uid']);
    if ($media === NULL || $user === NULL) {
      return static::permissionDenied();
    }

    $this->accountSwitcher->switchTo($user);

    $file = $this->mediaHelper->getFileForMedia($media);

    if ($timestamp) {
      $wopi_stamp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp);
      $file_stamp = \DateTimeImmutable::createFromFormat('U', $file->getChangedTime());

      if ($wopi_stamp != $file_stamp) {
        $this->logger->error('Conflict saving file ' . $id . ' wopi: ' . $wopi_stamp->format('c') . ' differs from file: ' . $file_stamp->format('c'));

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

    $save_reason = 'Saved by Collabora Online';
    $reasons = [];
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
    $this->logger->error('Save reason: ' . $save_reason);
    $media->setRevisionLogMessage($save_reason);
    $media->save();

    $payload = json_encode([
      'LastModifiedTime' => $mtime->format('c'),
    ]);

    $response = new Response(
      $payload,
      Response::HTTP_OK,
      ['content-type' => 'application/json']
    );

    $this->accountSwitcher->switchBack();
    return $response;
  }

  /**
   * The WOPI entry point.
   *
   * @param string $action
   *   One of 'info', 'content' or 'save', depending with path is visited.
   * @param string $id
   *   Media id from url.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object for headers and query parameters.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response to be consumed by Collabora Online.
   */
  public function wopi(string $action, string $id, Request $request): Response {
    $returnCode = Response::HTTP_BAD_REQUEST;
    switch ($action) {
      case 'info':
        return $this->wopiCheckFileInfo($id, $request);

      case 'content':
        return $this->wopiGetFile($id, $request);

      case 'save':
        return $this->wopiPutFile($id, $request);
    }

    $response = new Response(
      'Invalid WOPI action ' . $action,
      $returnCode,
      ['content-type' => 'text/plain']
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
   * @param int|string $expected_media_id
   *   Media id expected to be in the token payload.
   *   This could be a stringified integer like '123'.
   *
   * @return array|null
   *   Data decoded from the token, or NULL on failure or if the token has
   *   expired.
   */
  protected function verifyTokenForMediaId(
    #[\SensitiveParameter]
    string $token,
    int|string $expected_media_id,
  ): array|null {
    try {
      $values = $this->jwtTranscoder->decode($token);
    }
    catch (CollaboraNotAvailableException $e) {
      $this->logger->warning('A token cannot be decoded: @message', ['@mesage' => $e->getMessage()]);
      return NULL;
    }
    if ($values === NULL) {
      return NULL;
    }
    if ($values['fid'] !== $expected_media_id) {
      return NULL;
    }
    return $values;
  }

}
