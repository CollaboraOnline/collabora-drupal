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

use Drupal\collabora_online\Discovery\CollaboraDiscoveryFetcherInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Error;
use Drupal\media\MediaInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides route responses for the Collabora module.
 */
class ViewerController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly CollaboraDiscoveryFetcherInterface $discoveryFetcher,
    protected readonly JwtTranscoderInterface $jwtTranscoder,
    protected readonly RendererInterface $renderer,
    #[Autowire('logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AccountInterface $currentUser,
    TranslationInterface $string_translation,
  ) {
    $this->setStringTranslation($string_translation);
  }

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
  public function editor(MediaInterface $media, Request $request, $edit = FALSE): Response {
    try {
      // @todo Get client url for the correct MIME type.
      $discovery = $this->discoveryFetcher->getDiscovery();
      $wopi_client_url = $discovery->getWopiClientURL();
    }
    catch (CollaboraNotAvailableException $e) {
      $this->logger->warning(
        "Collabora Online is not available.<br>\n" . Error::DEFAULT_ERROR_MESSAGE,
        Error::decodeException($e) + [],
      );
      return new Response(
        (string) $this->t('The Collabora Online editor/viewer is not available.'),
        Response::HTTP_BAD_REQUEST,
        ['content-type' => 'text/plain'],
      );
    }
    if ($wopi_client_url === NULL) {
      return new Response(
        (string) $this->t('The Collabora Online editor/viewer is not available for this file type.'),
        Response::HTTP_BAD_REQUEST,
        ['content-type' => 'text/plain'],
      );
    }

    $current_request_scheme = $request->getScheme();
    if (parse_url($wopi_client_url, PHP_URL_SCHEME) !== $current_request_scheme) {
      $this->logger->error(
        "The current request uses '@current_request_scheme' url scheme, but the Collabora client url is '@wopi_client_url'.",
        [
          '@current_request_scheme' => $current_request_scheme,
          '@wopi_client_url' => $wopi_client_url,
        ],
      );
      return new Response(
        (string) $this->t('Viewer error: Protocol mismatch.'),
        Response::HTTP_BAD_REQUEST,
        ['content-type' => 'text/plain'],
      );
    }

    try {
      $render_array = $this->getViewerRender($media, $wopi_client_url, $edit);
    }
    catch (CollaboraNotAvailableException $e) {
      $this->logger->warning(
        "Cannot show the viewer/editor.<br>\n" . Error::DEFAULT_ERROR_MESSAGE,
        Error::decodeException($e) + [],
      );
      return new Response(
        (string) $this->t('The Collabora Online editor/viewer is not available.'),
        Response::HTTP_BAD_REQUEST,
        ['content-type' => 'text/plain'],
      );
    }

    return new Response((string) $this->renderer->renderRoot($render_array));
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
   *
   * @return array
   *   A stub render element.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The key to use by Collabora is empty or not configured.
   */
  protected function getViewerRender(MediaInterface $media, string $wopi_client, bool $can_write): array {
    /** @var array $cool_settings */
    $cool_settings = $this->configFactory->get('collabora_online.settings')->get('cool');

    if (empty($cool_settings['wopi_base'])) {
      throw new CollaboraNotAvailableException('The Collabora Online connection is not configured.');
    }

    // A trailing slash is optional in the configured URL.
    $wopi_base = rtrim($cool_settings['wopi_base'], '/');
    $expire_timestamp = $this->getExpireTimestamp();
    $access_token = $this->jwtTranscoder->encode(
      [
        'fid' => $media->id(),
        'uid' => $this->currentUser->id(),
        'wri' => $can_write,
      ],
      $expire_timestamp,
    );

    $render_array = [
      '#theme' => 'collabora_online_full',
      '#wopiClient' => $wopi_client,
      '#wopiSrc' => urlencode($wopi_base . '/cool/wopi/files/' . $media->id()),
      '#accessToken' => $access_token,
      // Convert to milliseconds.
      '#accessTokenTtl' => $expire_timestamp * 1000,
      '#allowfullscreen' => empty($cool_settings['allowfullscreen']) ? '' : 'allowfullscreen',
      '#closebutton' => 'true',
      '#attached' => [
        'library' => [
          'collabora_online/cool.frame',
        ],
      ],
    ];

    return $render_array;
  }

  /**
   * Gets a token expiration timestamp based on the configured TTL.
   *
   * @return float
   *   Expiration timestamp in seconds, with millisecond accuracy.
   */
  protected function getExpireTimestamp(): float {
    /** @var array $cool_settings */
    $cool_settings = $this->configFactory->get('collabora_online.settings')->get('cool');
    $ttl_seconds = $cool_settings['access_token_ttl'] ?? 0;
    // Set a fallback of 24 hours.
    $ttl_seconds = $ttl_seconds ?: 86400;

    return gettimeofday(TRUE) + $ttl_seconds;
  }

}
