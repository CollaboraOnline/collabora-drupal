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

namespace Drupal\collabora_online\Controller;

use Drupal\collabora_online\Cool\CollaboraDiscoveryInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\collabora_online\Jwt\JwtTranscoder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Error;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides route responses for the Collabora module.
 */
class ViewerController extends ControllerBase {

  public function __construct(
    protected readonly CollaboraDiscoveryInterface $discovery,
    protected readonly JwtTranscoder $tokenManager,
    protected readonly RendererInterface $renderer,
  ) {}

  /**
   * Returns a raw page for the iframe embed.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   * @param bool $edit
   *   TRUE to open Collabora Online in edit mode.
   *   FALSE to open Collabora Online in readonly mode.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response suitable for iframe, without the usual page decorations.
   */
  public function editor(MediaInterface $media, Request $request, $edit = FALSE) {
    $options = [
      'closebutton' => 'true',
    ];

    try {
      $wopi_client_url = $this->discovery->getWopiClientURL();
    }
    catch (CollaboraNotAvailableException $e) {
      $this->getLogger('cool')->warning(
        "Collabora Online is not available.<br>\n" . Error::DEFAULT_ERROR_MESSAGE,
        Error::decodeException($e) + [],
      );
      return new Response(
        (string) $this->t('The Collabora Online editor/viewer is not available.'),
        Response::HTTP_BAD_REQUEST,
        ['content-type' => 'text/plain'],
      );
    }

    $current_request_scheme = $request->getScheme();
    if (!str_starts_with($wopi_client_url, $current_request_scheme . '://')) {
      $this->getLogger('cool')->error($this->t(
        "The current request uses '@current_request_scheme' url scheme, but the Collabora client url is '@wopi_client_url'.",
        [
          '@current_request_scheme' => $current_request_scheme,
          '@wopi_client_url' => $wopi_client_url,
        ],
      ));
      return new Response(
        (string) $this->t('Viewer error: Protocol mismatch.'),
        Response::HTTP_BAD_REQUEST,
        ['content-type' => 'text/plain'],
      );
    }

    $render_array = $this->getViewerRender($media, $wopi_client_url, $edit, $options);

    $render_array['#theme'] = 'collabora_online_full';
    $render_array['#attached']['library'][] = 'collabora_online/cool.frame';

    $response = new Response();
    $response->setContent((string) $this->renderer->renderRoot($render_array));

    return $response;
  }

  /**
   * Gets a render array for a cool viewer.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to view / edit.
   * @param string $wopi_client
   *   The WOPI client url.
   * @param bool $can_write
   *   Whether this is a viewer (false) or an edit (true). Permissions will
   *   also be checked.
   * @param array{closebutton: bool} $options
   *   Options for the renderer. Current values:
   *     - "closebutton" if "true" will add a close box. (see COOL SDK)
   *
   * @return array
   *   A stub render element.
   */
  protected function getViewerRender(MediaInterface $media, string $wopi_client, bool $can_write, $options = NULL) {
    $default_config = $this->config('collabora_online.settings');
    $wopi_base = $default_config->get('cool')['wopi_base'];
    $allowfullscreen = $default_config->get('cool')['allowfullscreen'] ?? FALSE;

    $id = $media->id();

    $expire_timestamp = $this->tokenManager->getExpireTimestamp();
    $access_token = $this->tokenManager->tokenForFileId($id, $expire_timestamp, $can_write);

    $render_array = [
      '#wopiClient' => $wopi_client,
      '#wopiSrc' => urlencode($wopi_base . '/cool/wopi/files/' . $id),
      '#accessToken' => $access_token,
      // Convert to milliseconds.
      '#accessTokenTtl' => $expire_timestamp * 1000,
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
