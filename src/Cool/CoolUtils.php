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

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CoolUtils {
    /** Get the file from the Media entity */
    public static function getFile(Media $media) {
        $fid = $media->getSource()->getSourceFieldValue($media);
        $file = File::load($fid);

        return $file;
    }

    /** Get the file based on the entity id */
    public static function getFileById($id) {
        $media = \Drupal::entityTypeManager()->getStorage('media')->load($id);
        return CoolUtils::getFile($media);
    }

    public static function setMediaSource(Media $media, File $source) {
        $name = $media->getSource()->getSourceFieldDefinition($media->bundle->entity)->getName();
        $media->set($name, $source);
    }

    /** Obtain the signing key from the key storage */
    static function getKey() {
        $default_config = \Drupal::config('collabora_online.settings');
        $key_id = $default_config->get('collabora')['key_id'];

        $key = \Drupal::service('key.repository')->getKey($key_id)->getKeyValue();
        return $key;
    }

    /** Verify JWT token
     *
     *  Verification include:
     *  - matching $id with fid in the payload
     *  - verifying the expiration
     */
    public static function verifyTokenForId(
        #[\SensitiveParameter]
        string $token,
        $id
    ) {
        $key = static::getKey();
        try {
            $payload = JWT::decode($token, new Key($key, 'HS256'));

            if ($payload && ($payload->fid == $id) && ($payload->exp >= gettimeofday(true))) {
                return $payload;
            }
        } catch (\Exception $e) {
            \Drupal::logger('collabora')->error($e->getMessage());
        }
        return null;
    }

    /**
     * Return the TTL of the token in seconds, from the EPOCH.
     */
    public static function getAccessTokenTtl() {
        $default_config = \Drupal::config('collabora_online.settings');
        $ttl = $default_config->get('collabora')['access_token_ttl'];

        return gettimeofday(true) + $ttl;
    }

    /**
     * Create a JWT token for the Media with id $id, a $ttl, and an
     * eventual write permission.
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
     *  List of read only formats. Currently limited to the one Drupal
     *  accept.
     */
    const READ_ONLY = [
        'application/x-iwork-keynote-sffkey' => true,
        'application/x-iwork-pages-sffpages' => true,
        'application/x-iwork-numbers-sffnumbers' => true,
        'application/pdf' => true,
    ];

    /**
     * Return if we can edit that media file.
     *
     * There are few types that Collabora Online only views.
     */
    public static function canEdit(File $file) {
        $mimetype = $file->getMimeType();
        return !array_key_exists($mimetype, static::READ_ONLY);
    }

    /** Return the mime type for the document.
     *
     *  Drupal will figure it out for us.
     */
    public static function getDocumentType(File $file) {
        return $file->getMimeType();
    }

    /** Return the editor / viewer Drupal URL from the routes configured. */
    public static function getEditorUrl(Media $media, $can_write = false) {
        if ($can_write) {
            return Url::fromRoute('collabora-online.edit', ['media' => $media->id()]);
        } else {
            return Url::fromRoute('collabora-online.view', ['media' => $media->id()]);
        }
    }
}
