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
use Drupal\user\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Drupal\collabora_online\Cool\CoolRequest;

class CoolUtils {
    /** Get the permissions for the user
     * @paranm $user {User|AccountProxy} The user to check the roles from.
     */
    public static function getUserPermissions($user) {
        $default_config = \Drupal::config('collabora_online.settings');
        $viewer_role = $default_config->get('cool')['viewer_role'];
        $collaborator_role = $default_config->get('cool')['collaborator_role'];
        $administrator_role = $default_config->get('cool')['administrator_role'];

        $roles = $user->getRoles();

        $is_admin = in_array($administrator_role, $roles);
        $is_collaborator = in_array($collaborator_role, $roles) || $is_admin;
        $is_viewer = in_array($viewer_role, $roles) || $is_collaborator;

        $permissions = [
            'is_anonymous' => in_array('anonymous', $roles),
            'is_admin' => $is_admin,
            'is_collaborator' => $is_collaborator,
            'is_viewer' => $is_viewer,
        ];

        return $permissions;
    }

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

    /** Get the media source entity for the file.
     *  It will get the first in the list if there are more than one.
     */
    public static function getMediaSourceForFile(File $file) {
        $media_entities = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties([
            'field_media_document' => $file->id(),
        ]);
        if (!is_array($media_entities)) {
            \Drupal::logger('cool')->error('Media for file ' . $file->id() . ' not found.');
            return NULL;
        }

        return array_pop($media_entities);
    }

    /** Obtain the signing key from the key storage */
    static function getKey() {
        $default_config = \Drupal::config('collabora_online.settings');
        $key_id = $default_config->get('cool')['key_id'];

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
            \Drupal::logger('cool')->error($e->getMessage());
        }
        return null;
    }

    /**
     * Return the TTL of the token in seconds, from the EPOCH.
     */
    public static function getAccessTokenTtl() {
        $default_config = \Drupal::config('collabora_online.settings');
        $ttl = $default_config->get('cool')['access_token_ttl'];

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

    /**
     * Get a render array for a cool viewer.
     *
     * @param Media $media
     *   The media entity to view / edit
     *
     * @param bool $can_write
     *   Whether this is a viewer (false) or an edit (true). Permissions will
     *   also be checked.
     *
     * @param array $options
     *   Options for the renderer. Current values:
     *     - "closebutton" if "true" will add a close box. (see COOL SDK)
     */
    public static function getViewerRender(Media $media, bool $can_write, $options = null) {
        $default_config = \Drupal::config('collabora_online.settings');
        $wopi_base = $default_config->get('cool')['wopi_base'];

        $req = new CoolRequest();
        $wopi_client = $req->getWopiClientURL();
        if ($wopi_client === null) {
            return [
                'error' => t('The Collabora Online server is not available: ') . $req->errorString(),
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
            '#accessTokenTtl' => $ttl * 1000, // It's in usec. The JWT is in sec.
        ];
        if ($options) {
            if (isset($options['closebutton']) && $options['closebutton'] == 'true') {
                $render_array['#closebutton'] = 'true';
            }
        }

        return $render_array;
    }


}
