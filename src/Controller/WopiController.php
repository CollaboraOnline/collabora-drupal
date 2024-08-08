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

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\collabora_online\Cool\CoolUtils;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides WOPI route responses for the Collabora module.
 */
class WopiController extends ControllerBase {

    static function permissionDenied() {
        return new Response(
            'Authentication failed.',
            Response::HTTP_FORBIDDEN,
            ['content-type' => 'text/plain'],
        );
    }

    function wopiCheckFileInfo(string $id, Request $request) {
        $token = $request->query->get('access_token');

        $jwt_payload = CoolUtils::verifyTokenForId($token, $id);
        if ($jwt_payload == null) {
            return static::permissionDenied();
        }

        $file = CoolUtils::getFileById($id);
        $mtime = date_create_immutable_from_format('U', $file->getChangedTime());
        $user = User::load($jwt_payload->uid);
        $permissions = CoolUtils::getUserPermissions($user);
        $can_write = $jwt_payload->wri;

        if ($can_write && $can_write != $permissions['is_collaborator']) {
            \Drupal::logger('cool')->error('Token and user permissions do not match.');
            return static::permissionDenied();
        }

        $user_picture = $user->user_picture->entity;
        if ($user_picture) {
            $avatarUrl = \Drupal::service('file_url_generator')->generateAbsoluteString($user_picture->getFileUri());
        }

        $payload = [
            'BaseFileName' => $file->getFilename(),
            'Size' => $file->getSize(),
            'LastModifiedTime' => $mtime->format('c'),
            'UserId' => $jwt_payload->uid,
            'UserFriendlyName' => $user->getDisplayName(),
            'UserExtraInfo' => [
                'avatar' => $avatarUrl,
                'mail' => $user->getEmail(),
            ],
            'UserCanWrite' => $can_write,
            'IsAdminUser' => $permissions['is_admin'],
            'IsAnonymousUser' => $permissions['is_anonymous']
        ];

        $jsonPayload = json_encode($payload);

        $response = new Response(
            $jsonPayload,
            Response::HTTP_OK,
            ['content-type' => 'application/json']
        );
        return $response;
    }

    function wopiGetFile(string $id, Request $request) {
        $token = $request->query->get('access_token');

        $jwt_payload = CoolUtils::verifyTokenForId($token, $id);
        if ($jwt_payload == null) {
            return static::permissionDenied();
        }

        $user = User::load($jwt_payload->uid);
        $accountSwitcher = \Drupal::service('account_switcher');
        $accountSwitcher->switchTo($user);

        $file = CoolUtils::getFileById($id);
        $mimetype = $file->getMimeType();

        $response = new BinaryFileResponse(
            $file->getFileUri(),
            Response::HTTP_OK,
            ['content-type' => $mimetype]
        );
        $accountSwitcher->switchBack();
        return $response;
    }

    function wopiPutFile(string $id, Request $request) {
        $token = $request->query->get('access_token');
        $timestamp = $request->headers->get('x-cool-wopi-timestamp');
        $modified_by_user = $request->headers->get('x-cool-wopi-ismodifiedbyuser') == 'true';
        $autosave = $request->headers->get('x-cool-wopi-isautosave') == 'true';
        $exitsave = $request->headers->get('x-cool-wopi-isexitsave') == 'true';

        $jwt_payload = CoolUtils::verifyTokenForId($token, $id);
        if ($jwt_payload == null || !$jwt_payload->wri) {
            return static::permissionDenied();
        }

        $fs = \Drupal::service('file_system');

        $media = \Drupal::entityTypeManager()->getStorage('media')->load($id);
        $user = User::load($jwt_payload->uid);

        $accountSwitcher = \Drupal::service('account_switcher');
        $accountSwitcher->switchTo($user);

        $file = CoolUtils::getFile($media);

        if ($timestamp) {
            $wopi_stamp = date_create_immutable_from_format(\DateTimeInterface::ISO8601, $timestamp);
            $file_stamp = date_create_immutable_from_format('U', $file->getChangedTime());

            if ($wopi_stamp != $file_stamp) {
                \Drupal::logger('cool')->error('Conflict saving file ' . $id . ' wopi: ' . $wopi_stamp->format('c') . ' differs from file: ' . $file_stamp->format('c'));

                return new Response(
                    json_encode([ 'COOLStatusCode' => 1010 ]),
                    Response::HTTP_CONFLICT,
                    ['content-type' => 'application/json'],
                );
            }
        }

        $dir = $fs->dirname($file->getFileUri());
        $dest = $dir . '/' . $file->getFilename();

        $content = $request->getContent();
        $owner_id = $file->getOwnerId();
        $uri = $fs->saveData($content, $dest, FileSystemInterface::EXISTS_RENAME);

        $file = File::create(['uri' => $uri]);
        $file->setOwnerId($owner_id);
        if (is_file($dest)) {
            $file->setFilename($fs->basename($dest));
        }
        $file->setPermanent();
        $file->setSize(strlen($content));
        $file->save();
        $mtime = date_create_immutable_from_format('U', $file->getChangedTime());

        CoolUtils::setMediaSource($media, $file);
        $media->setRevisionUser($user);
        $media->setRevisionCreationTime(\Drupal::service('datetime.time')->getRequestTime());

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
            $save_reason .= ' (' . implode(', ', $reasons)  . ')';
        }
        \Drupal::logger('cool')->error('Save reason: ' . $save_reason);
        $media->setRevisionLogMessage($save_reason);
        $media->save();

        $payload =  json_encode([
            'LastModifiedTime' => $mtime->format('c'),
        ]);

        $response = new Response(
            $payload,
            Response::HTTP_OK,
            ['content-type' => 'application/json']
        );

        $accountSwitcher->switchBack();
        return $response;
    }

    /**
     * The WOPI entry point.
     *
     * action: 'info', 'content' or 'save'.
     * id: the ID of the media.
     * request: The request as originating.
     *
     * @return Response
     */
    public function wopi(string $action, string $id, Request $request) {
        $returnCode = Response::HTTP_BAD_REQUEST;
        switch ($action) {
        case 'info':
            return $this->wopiCheckFileInfo($id, $request);
            break;
        case 'content':
            return $this->wopiGetFile($id, $request);
            break;
        case 'save':
            return $this->wopiPutFile($id, $request);
            break;
        }

        $response = new Response(
            'Invalid WOPI action ' . $action,
            $returnCode,
            ['content-type' => 'text/plain']
        );
        return $response;
    }
}

?>
