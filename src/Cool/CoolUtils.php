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

namespace Drupal\collabora_online\Cool;

use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Class with various static methods.
 */
class CoolUtils {

  /**
   * Gets the file referenced by a media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media entity.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if not found.
   */
  public static function getFile(Media $media) {
    $fid = $media->getSource()->getSourceFieldValue($media);
    $file = File::load($fid);

    return $file;
  }

  /**
   * Gets a file based on the media id.
   *
   * @param int|string $id
   *   Media id which might be in strong form like '123'.
   *
   * @return \Drupal\file\FileInterface|null
   *   File referenced by the media entity, or NULL if not found.
   */
  public static function getFileById($id) {
    /** @var \Drupal\media\MediaInterface|null $media */
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($id);
    return CoolUtils::getFile($media);
  }

  /**
   * Sets the file entity reference for a media entity.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media entity to be modified.
   * @param \Drupal\file\Entity\File $source
   *   File entity to reference.
   */
  public static function setMediaSource(Media $media, File $source) {
    $name = $media->getSource()->getSourceFieldDefinition($media->bundle->entity)->getName();
    $media->set($name, $source);
  }

  /**
   * Obtains the signing key from the key storage.
   *
   * @return string
   *   The key value.
   */
  public static function getKey() {
    $default_config = \Drupal::config('collabora_online.settings');
    $key_id = $default_config->get('cool')['key_id'];

    $key = \Drupal::service('key.repository')->getKey($key_id)->getKeyValue();
    return $key;
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
   * @param int|string $id
   *   Media id for which the token was created.
   *   This could be in string form like '123'.
   *
   * @return \stdClass|null
   *   Data decoded from the token, or NULL on failure or if the token has
   *   expired.
   */
  public static function verifyTokenForId(
    #[\SensitiveParameter]
    string $token,
    $id,
  ) {
    $key = static::getKey();
    try {
      $payload = JWT::decode($token, new Key($key, 'HS256'));

      if ($payload && ($payload->fid == $id) && ($payload->exp >= gettimeofday(TRUE))) {
        return $payload;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('cool')->error($e->getMessage());
    }
    return NULL;
  }

  /**
   * Gets the TTL of the token in seconds, from the EPOCH.
   *
   * @return int
   *   Token TTL in seconds.
   */
  public static function getAccessTokenTtl() {
    $default_config = \Drupal::config('collabora_online.settings');
    $ttl = $default_config->get('cool')['access_token_ttl'];

    return gettimeofday(TRUE) + $ttl;
  }

  /**
   * Creates a JWT token for a media entity.
   *
   * The token will carry the following:
   *
   * - fid: the Media id in Drupal.
   * - uid: the User id for the token. Permissions should be checked
   *   whenever.
   * - exp: the expiration time of the token.
   * - wri: if true, then this token has write permissions.
   *
   * The signing key is stored in Drupal key management.
   *
   * @param int|string $id
   *   Media id, which could be in string form like '123'.
   * @param int $ttl
   *   Access token TTL in seconds.
   * @param bool $can_write
   *   TRUE if the token is for an editor in write/edit mode.
   *
   * @return string
   *   The access token.
   */
  public static function tokenForFileId($id, $ttl, $can_write = FALSE) {
    $payload = [
      "fid" => $id,
      "uid" => \Drupal::currentUser()->id(),
      "exp" => $ttl,
      "wri" => $can_write,
    ];
    $key = static::getKey();
    $jwt = JWT::encode($payload, $key, 'HS256');

    return $jwt;
  }

  /**
   * List of read only formats. Currently limited to the one Drupal accept.
   */
  const READ_ONLY = [
    'application/x-iwork-keynote-sffkey' => TRUE,
    'application/x-iwork-pages-sffpages' => TRUE,
    'application/x-iwork-numbers-sffnumbers' => TRUE,
  ];

  /**
   * Determines if we can edit that media file.
   *
   * There are few types that Collabora Online only views.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   *
   * @return bool
   *   TRUE if the file has a file type that is supported for editing.
   *   FALSE if the file can only be opened as read-only.
   */
  public static function canEdit(File $file) {
    $mimetype = $file->getMimeType();
    return !array_key_exists($mimetype, static::READ_ONLY);
  }

  /**
   * Gets the mime type for the document.
   *
   * Drupal will figure it out for us.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   *
   * @return string|null
   *   The mime type, or NULL if it cannot be determined.
   */
  public static function getDocumentType(File $file) {
    return $file->getMimeType();
  }

  /**
   * Gets the editor / viewer Drupal URL from the routes configured.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media entity that holds the file to open in the editor.
   * @param bool $can_write
   *   TRUE for an edit url, FALSE for a read-only preview url.
   *
   * @return \Drupal\Core\Url
   *   Editor url to visit as full-page, or to embed in an iframe.
   */
  public static function getEditorUrl(Media $media, $can_write = FALSE) {
    if ($can_write) {
      return Url::fromRoute('collabora-online.edit', ['media' => $media->id()]);
    }
    else {
      return Url::fromRoute('collabora-online.view', ['media' => $media->id()]);
    }
  }

  /**
   * Gets a render array for a cool viewer.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media entity to view / edit.
   * @param bool $can_write
   *   Whether this is a viewer (false) or an edit (true). Permissions will
   *   also be checked.
   * @param array{closebutton: bool} $options
   *   Options for the renderer. Current values:
   *     - "closebutton" if "true" will add a close box. (see COOL SDK)
   *
   * @return array|array{error: string}
   *   A stub render element array, or an array with an error on failure.
   */
  public static function getViewerRender(Media $media, bool $can_write, $options = NULL) {
    $default_config = \Drupal::config('collabora_online.settings');
    $wopi_base = $default_config->get('cool')['wopi_base'];
    $allowfullscreen = $default_config->get('cool')['allowfullscreen'] ?? FALSE;

    $req = new CoolRequest();
    try {
      $wopi_client = $req->getWopiClientURL();
    }
    catch (CollaboraNotAvailableException $e) {
      return [
        'error' => t('The Collabora Online server is not available: @message', [
          '@message' => $e->getCode() . ': ' . $e->getMessage(),
        ]),
      ];
    }

    $id = $media->id();

    $ttl = static::getAccessTokenTtl();
    if ($ttl == 0) {
      $ttl = 86400;
    }
    $access_token = static::tokenForFileId($id, $ttl, $can_write);

    $render_array = [
      '#wopiClient' => $wopi_client,
      '#wopiSrc' => urlencode($wopi_base . '/cool/wopi/files/' . $id),
      '#accessToken' => $access_token,
      // It's in usec. The JWT is in sec.
      '#accessTokenTtl' => $ttl * 1000,
      '#allowfullscreen' => $allowfullscreen ? 'allowfullscreen' : '',
    ];
    if ($options) {
      if (isset($options['closebutton']) && $options['closebutton'] == 'true') {
        $render_array['#closebutton'] = 'true';
      }
    }

    return $render_array;
  }

}
