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

use Drupal\collabora_online\Cool\CollaboraDiscovery;
use Drupal\collabora_online\Cool\CoolUtils;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
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

  /**
   * The controller constructor.
   *
   * @param \Drupal\collabora_online\Cool\CollaboraDiscovery $discovery
   *   Service to fetch a WOPI client URL.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    protected readonly CollaboraDiscovery $discovery,
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

    $render_array = CoolUtils::getViewerRender($media, $wopi_client_url, $edit, $options);

    $render_array['#theme'] = 'collabora_online_full';
    $render_array['#attached']['library'][] = 'collabora_online/cool.frame';

    $response = new Response();
    $response->setContent((string) $this->renderer->renderRoot($render_array));

    return $response;
  }

}
