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

use Drupal\collabora_online\Exception\CollaboraJwtKeyException;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\collabora_online\Storage\WopiSettingsStorageInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides WOPI route responses Collabora settings page.
 */
class WopiSettingsController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly JwtTranscoderInterface $jwtTranscoder,
    protected readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
    protected readonly WopiSettingsStorageInterface $wopiSettingsStorage,
  ) {}

  /**
   * Gets a list of stored settings files.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The WOPI request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function info(Request $request): Response {
    $this->verify($request);
    $type = $request->get('type');
    if ($type === NULL) {
      throw new AccessDeniedHttpException('Missing type.');
    }
    $response_data = $this->getInfoForType($type);
    $this->logger->debug(
      'Wopi settings info:<br>
<h3>Query</h3>
<pre>@query</pre>
<h3>Response data</h3>
<pre>@response</pre>',
      [
        '@query' => Yaml::encode(array_diff_key($request->query->all(), ['access_token' => TRUE])),
        '@response' => Yaml::encode($response_data),
      ],
    );
    return new JsonResponse($response_data, headers: ['content-type' => 'application/json']);
  }

  /**
   * Builds response data for the 'info' request.
   *
   * @param 'userconfig'|'systemconfig' $type
   *   The configuration type.
   *
   * @return array
   *   Response data.
   */
  protected function getInfoForType(string $type): array {
    $response_data = [];
    $response_data['kind'] = match ($type) {
      'userconfig' => 'user',
      'systemconfig' => 'shared',
    };

    $stamps = $this->wopiSettingsStorage->list("/settings/$type/");
    foreach ($stamps as $wopi_file_id => $stamp) {
      $type_pattern = preg_quote($type, '@');
      if (!preg_match("@^/settings/$type_pattern/(\w+)/\w+\.\w+$@", $wopi_file_id, $matches)) {
        continue;
      }
      $category = $matches[1];
      $download_url = $this->buildDownloadUrl($wopi_file_id);
      $response_data[$category][] = [
        'stamp' => $stamp,
        'uri' => $download_url->toString(),
      ];
    }

    return $response_data;
  }

  /**
   * Builds a WOPI url to download a single settings file.
   *
   * @param string $wopi_file_id
   *   File identifier as "/settings/$type/$category/$name.$extension".
   *
   * @return \Drupal\Core\Url
   *   Url object with the correct WOPI base url.
   */
  protected function buildDownloadUrl(string $wopi_file_id): Url {
    $wopi_base = $this->configFactory->get('collabora_online.settings')
      ->get('cool.wopi_base');
    if (!$wopi_base || !is_string($wopi_base)) {
      throw new HttpException(500, 'The requested functionality is not available.');
    }
    return Url::fromRoute('collabora-online.wopi.settings.download')
      // The token is added automatically by the WOPI client.
      ->setOption('query', ['fileId' => $wopi_file_id])
      ->setOption('base_url', rtrim($wopi_base, '/'))
      ->setAbsolute();
  }

  /**
   * Downloads a settings file content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The WOPI request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Not found or no access.
   */
  public function download(Request $request): Response {
    $this->verify($request);
    $wopi_file_id = $this->readWopiFileId($request);
    $content = $this->wopiSettingsStorage->read($wopi_file_id);
    if ($content === NULL) {
      throw new NotFoundHttpException('Settings file not found.');
    }
    $this->logger->debug(
      'Wopi settings download:<br>
<h3>Query</h3>
<pre>@query</pre>
<h3>Content</h3>
<pre>@content</pre>',
      [
        '@query' => Yaml::encode(array_diff_key($request->query->all(), ['access_token' => TRUE])),
        '@content' => $content,
      ],
    );
    // @todo Detect MIME type and set 'content-type' header.
    return new Response($content);
  }

  /**
   * Uploads a settings file.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The WOPI request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Not found or no access.
   */
  public function upload(Request $request): Response {
    $this->verify($request);
    $wopi_file_id = $this->readWopiFileId($request);
    $content = $request->getContent();
    $this->logger->debug(
      'Wopi settings upload:<br>
<h3>Query</h3>
<pre>@query</pre>
<h3>Content</h3>
<pre>@content</pre>',
      [
        '@query' => Yaml::encode(array_diff_key($request->query->all(), ['access_token' => TRUE])),
        '@content' => $request->getContent(),
      ],
    );
    $stamp = uniqid();
    $is_new = $this->wopiSettingsStorage->write($wopi_file_id, $content, $stamp);
    return new JsonResponse(
      [
        'success' => 'success',
        'filename' => basename($wopi_file_id),
        'details' => [
          'stamp' => $stamp,
          'uri' => $wopi_file_id,
        ],
      ],
      $is_new ? Response::HTTP_CREATED : Response::HTTP_OK,
      ['content-type' => 'application/json'],
    );
  }

  /**
   * Reads and validates the 'fileId' query parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The value of the 'fileId' query parameter.
   */
  protected function readWopiFileId(Request $request): string {
    $wopi_file_id = $request->query->get('fileId');
    if ($wopi_file_id === NULL) {
      throw new BadRequestHttpException("Missing 'fileId' query parameter.");
    }
    // For now, all known settings file paths match the simple pattern as below.
    if (
      !is_string($wopi_file_id) ||
      !preg_match("@^/settings/(userconfig|systemconfig)/\w+/\w+\.\w+$@", $wopi_file_id)
    ) {
      throw new BadRequestHttpException("Invalid value for 'fileId' query parameter.");
    }
    return $wopi_file_id;
  }

  /**
   * Verifies that a request has a valid token.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The WOPI request to verify.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Bad or missing token.
   */
  protected function verify(Request $request): void {
    if (!$this->wopiSettingsStorage->isAvailable()) {
      throw new HttpException(Response::HTTP_NOT_IMPLEMENTED, 'The settings storage is not available.');
    }
    $token = $request->get('access_token');
    if ($token === NULL) {
      throw new AccessDeniedHttpException('Missing access token.');
    }
    if (!is_string($token)) {
      // A malformed request could have a non-string value for access_token.
      throw new AccessDeniedHttpException(sprintf('Expected a string access token, found %s.', gettype($token)));
    }
    try {
      $jwt_payload = $this->jwtTranscoder->decode($token);
    }
    catch (CollaboraJwtKeyException $e) {
      $this->logger->warning('A token cannot be decoded: @message', ['@message' => $e->getMessage()]);
      throw new AccessDeniedHttpException('Token verification is not possible right now.');
    }
    if ($jwt_payload === NULL) {
      throw new AccessDeniedHttpException('Bad token');
    }
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($jwt_payload['uid']);
    if ($user === NULL) {
      throw new AccessDeniedHttpException('User not found.');
    }
  }

}
