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

use Drupal\collabora_online\Cool\CollaboraDiscovery;
use Drupal\collabora_online\Cool\CoolUtils;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\Entity\Media;
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
   * @param \Drupal\media\Entity\Media $media
   *   Media entity.
   * @param bool $edit
   *   TRUE to open Collabora Online in edit mode.
   *   FALSE to open Collabora Online in readonly mode.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response suitable for iframe, without the usual page decorations.
   */
  public function editor(Media $media, $edit = FALSE) {
    $options = [
      'closebutton' => 'true',
    ];

    try {
      $wopi_client_url = $this->discovery->getWopiClientURL();
    }
    catch (CollaboraNotAvailableException $e) {
      $error_msg = $this->t('The Collabora Online server is not available: @message', [
        '@message' => $e->getCode() . ': ' . $e->getMessage(),
      ]);
      $error_msg = (string) $this->t('Viewer error: @message', [
        '@message' => $error_msg,
      ]);
      $this->getLogger('cool')->error($error_msg);
      return new Response(
        $error_msg,
        Response::HTTP_BAD_REQUEST,
        ['content-type' => 'text/plain'],
      );
    }

    $render_array = CoolUtils::getViewerRender($media, $wopi_client_url, $edit, $options);

    $render_array['#theme'] = 'collabora_online_full';
    $render_array['#attached']['library'][] = 'collabora_online/cool.frame';

    $response = new Response();
    $response->setContent($this->renderer->renderRoot($render_array));

    return $response;
  }

}
