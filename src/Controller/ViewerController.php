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
use Drupal\Core\Render\RendererInterface;
use Drupal\collabora_online\Cool\CoolUtils;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides route responses for the Collabora module.
 */
class ViewerController extends ControllerBase {

    private $renderer;

    /**
     * The controller constructor.
     */
    public function __construct(RendererInterface $renderer) {
        $this->renderer = $renderer;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): self {
        return new self(
            $container->get('renderer'),
        );
    }

    /**
     * Returns a raw page for the iframe embed.
     *
     * Set edit to true for an editor.
     *
     * @return Response
     */
    public function editor(Media $media, $edit = false) {
        $options = [
            'closebutton' => 'true',
        ];

        $user = \Drupal::currentUser();
        $permissions = CoolUtils::getUserPermissions($user);

        if (!$permissions['is_viewer']) {
            $error_msg = 'Authentication failed.';
            \Drupal::logger('cool')->error($error_msg);
            return new Response(
                $error_msg,
                Response::HTTP_FORBIDDEN,
                ['content-type' => 'text/plain']
            );
        }

        /* Make sure that the user is a collaborator if edit is true */
        $edit = $edit && $permissions['is_collaborator'];

        $render_array = CoolUtils::getViewerRender($media, $edit, $options);

        if (!$render_array ||  array_key_exists('error', $render_array)) {
            $error_msg = 'Viewer error: ' . $render_array ? $render_array['error'] : 'NULL';
            \Drupal::logger('cool')->error($error_msg);
            return new Response(
                $error_msg,
                Response::HTTP_BAD_REQUEST,
                ['content-type' => 'text/plain']
            );
        }

        $render_array['#theme'] = 'collabora_online_full';
        $render_array['#attached']['library'][] = 'collabora_online/cool.frame';

        $response = new Response();
        $response->setContent($this->renderer->renderRoot($render_array));

        return $response;
    }
}

?>
