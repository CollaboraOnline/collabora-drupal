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
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides WOPI route responses Collabora settings page.
 */
class WopiSettingsController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly JwtTranscoderInterface $jwtTranscoder,
    #[Autowire('logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * The WOPI entry point.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object for headers and query parameters.
   * @param string $action
   *   One of 'info'.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response to be consumed by Collabora Online.
   */
  public function wopi(Request $request, string $action = 'info'): Response {
    $token = $request->get('access_token');
    if ($token === NULL) {
      throw new AccessDeniedHttpException('Missing access token.');
    }
    if (!is_string($token)) {
      // A malformed request could have a non-string value for access_token.
      throw new AccessDeniedHttpException(sprintf('Expected a string access token, found %s.', gettype($token)));
    }
    $jwt_payload = $this->verifyToken($token);

    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($jwt_payload['uid']);
    if ($user === NULL) {
      throw new AccessDeniedHttpException('User not found.');
    }

    switch ($action) {
      case 'info':
        $type = $request->get('type');
        if ($type === NULL) {
          throw new AccessDeniedHttpException('Missing type.');
        }
        return new JsonResponse(['kind' => $type], headers: ['content-type' => 'application/json']);

      case 'upload':
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
        return new JsonResponse([], headers: ['content-type' => 'application/json']);
    }

    return new Response(
      'Invalid WOPI action ' . $action,
      Response::HTTP_BAD_REQUEST,
      ['content-type' => 'text/plain'],
    );
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
   *
   * @return array
   *   Data decoded from the token.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   The token is malformed, invalid or has expired.
   */
  protected function verifyToken(
    #[\SensitiveParameter]
    string $token,
  ): array {
    try {
      $values = $this->jwtTranscoder->decode($token);
    }
    catch (CollaboraJwtKeyException $e) {
      $this->logger->warning('A token cannot be decoded: @message', ['@message' => $e->getMessage()]);
      throw new AccessDeniedHttpException('Token verification is not possible right now.');
    }
    if ($values === NULL) {
      throw new AccessDeniedHttpException('Bad token');
    }
    return $values;
  }

}
