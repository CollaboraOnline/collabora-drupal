<?php

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Kernel\Controller;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\collabora_online\Kernel\CollaboraKernelTestBase;
use Drupal\Tests\collabora_online\Traits\KernelTestLoggerTrait;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class with shared methods to test WOPI requests.
 */
abstract class WopiControllerTestBase extends CollaboraKernelTestBase {

  use KernelTestLoggerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'collabora_online_test',
  ];

  /**
   * The user with access to perform operations.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * The user with access to perform operations.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $fileOwner;

  /**
   * The media where to perform operations.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected MediaInterface $media;

  /**
   * The source file.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface $file;

  /**
   * The test logger channel.
   *
   * @var \ColinODell\PsrTestLogger\TestLogger
   */
  protected TestLogger $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpLogger();

    $collabora_settings = \Drupal::configFactory()->getEditable('collabora_online.settings');
    $cool = $collabora_settings->get('cool');
    $cool['key_id'] = 'collabora';
    $collabora_settings->set('cool', $cool)->save();

    // Make sure that ids for different entity types are distinguishable.
    // This will reveal bugs where one id gets mixed up for another.
    \Drupal::database()->query("ALTER TABLE {media} AUTO_INCREMENT = 1000");
    \Drupal::database()->query("ALTER TABLE {file_managed} AUTO_INCREMENT = 2000");

    $this->user = $this->createUser([
      'access content',
      'edit any document in collabora',
    ]);
    // Create a separate user as file owner, to verify that the file owner id is
    // set correctly.
    $this->fileOwner = $this->createUser([]);
    $this->media = $this->createMediaEntity(
      'document',
      ['uid' => $this->user->id()],
      ['uid' => $this->fileOwner->id()],
    );
    $fid = $this->media->getSource()->getSourceFieldValue($this->media);
    $this->file = File::load($fid);

    $this->setCurrentUser($this->user);
  }

  /**
   * Creates WOPI requests for different routes, with some shared parameters.
   *
   * This can be used for tests where each route is expected to have the same
   * response.
   *
   * @param int|null $media_id
   *   Media entity id, if different from the default.
   * @param int|null $user_id
   *   User id, if different from the default.
   * @param array $token_payload
   *   Explicit token payload values.
   *   This can be used to cause a bad token.
   *
   * @return array<string, \Symfony\Component\HttpFoundation\Request>
   *   Requests keyed by a distinguishable name.
   */
  protected function createRequests(?int $media_id = NULL, ?int $user_id = NULL, array $token_payload = []): array {
    $create_request = fn (string $uri_suffix, string $method = 'GET', bool $write = FALSE) => $this->createRequest(
      $uri_suffix,
      $method,
      $media_id,
      $user_id,
      $write,
      $token_payload,
    );
    return [
      'info' => $create_request(''),
      'file' => $create_request('/contents'),
      'save' => $create_request('/contents', 'POST', TRUE),
    ];
  }

  /**
   * Creates a WOPI request.
   *
   * @param string $uri_suffix
   *   Suffix to append to the WOPI media url.
   * @param string $method
   *   E.g. 'GET' or 'POST'.
   * @param int|null $media_id
   *   Media entity id, if different from the default.
   * @param int|null $user_id
   *   User id, if different from the default.
   * @param bool $write
   *   TRUE if write access is requested.
   * @param array $token_payload
   *   Explicit token payload values.
   *   This can be used to cause a bad token.
   * @param string|null $content
   *   Request content.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createRequest(
    string $uri_suffix = '',
    string $method = 'GET',
    ?int $media_id = NULL,
    ?int $user_id = NULL,
    bool $write = FALSE,
    array $token_payload = [],
    ?string $content = NULL,
  ): Request {
    $media_id ??= (int) $this->media->id();
    $user_id ??= (int) $this->user->id();
    $uri = '/cool/wopi/files/' . $media_id . $uri_suffix;
    $token = $this->createAccessToken($media_id, $user_id, $write, $token_payload);
    $parameters = [
      'access_token' => $token,
      'access_token_ttl' => '0',
    ];
    return Request::create($uri, $method, $parameters, content: $content);
  }

  /**
   * Retrieves an encoded access token.
   *
   * @param int|null $fid
   *   The file id.
   * @param int|null $uid
   *   The user id.
   * @param bool $write
   *   The write permission.
   * @param array $payload
   *   Explicit payload values.
   *   This can be used to cause a bad token.
   *
   * @return string
   *   The enconded token.
   */
  protected function createAccessToken(?int $fid = NULL, ?int $uid = NULL, bool $write = FALSE, array $payload = []): string {
    /** @var \Drupal\collabora_online\Jwt\JwtTranscoderInterface $transcoder */
    $transcoder = \Drupal::service(JwtTranscoderInterface::class);
    $expire_timestamp = gettimeofday(TRUE) + 1000;
    $payload += [
      'fid' => (string) ($fid ?? $this->media->id()),
      'uid' => (string) ($uid ?? $this->user->id()),
      'wri' => $write,
      'exp' => $expire_timestamp,
    ];
    return $transcoder->encode($payload, $expire_timestamp);
  }

  /**
   * Asserts a successful json response given a request.
   *
   * @param array $expected_data
   *   The expected response JSON data.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertJsonResponseOk(array $expected_data, Request $request, string $message = ''): void {
    $this->assertJsonResponse(Response::HTTP_OK, $expected_data, $request, $message);
  }

  /**
   * Asserts a json response given a request.
   *
   * @param int $expected_code
   *   The expected response status code.
   * @param array $expected_data
   *   The expected response JSON data.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertJsonResponse(int $expected_code, array $expected_data, Request $request, string $message = ''): void {
    $response = $this->handleRequest($request);
    $content = $response->getContent();
    $this->assertIsString($content);
    $extended_message = $message . "\n" . substr($content, 0, 3000);
    $this->assertEquals($expected_code, $response->getStatusCode(), $extended_message);
    $this->assertEquals('application/json', $response->headers->get('Content-Type'), $extended_message);
    $data = Json::decode($content);
    $this->assertNotNull($data, $extended_message);
    $this->assertSame($expected_data, $data, $message);
  }

  /**
   * Asserts an access denied response given a request.
   *
   * @param string $expected_response_message
   *   Message expected to be in the response.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $assertion_message
   *   Message to distinguish this from other assertions.
   */
  protected function assertAccessDeniedResponse(string $expected_response_message, Request $request, string $assertion_message = ''): void {
    $this->assertResponse(
      Response::HTTP_FORBIDDEN,
      $expected_response_message,
      'text/plain',
      $request,
      $assertion_message,
    );
  }

  /**
   * Asserts a failure response given a request.
   *
   * @param string $expected_response_message
   *   Message expected to be in the response.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $assertion_message
   *   Message to distinguish this from other assertions.
   */
  protected function assertNotFoundResponse(
    string $expected_response_message,
    Request $request,
    string $assertion_message = '',
  ): void {
    $this->assertResponse(
      Response::HTTP_NOT_FOUND,
      $expected_response_message,
      'text/plain',
      $request,
      $assertion_message,
    );
  }

  /**
   * Asserts status code and content in a response given a request.
   *
   * @param int $expected_code
   *   The expected response status code.
   * @param string $expected_content
   *   The expected response content.
   * @param string $expected_content_type
   *   The type of content of the response.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to perform.
   * @param string $message
   *   Message to distinguish this from other assertions.
   */
  protected function assertResponse(int $expected_code, string $expected_content, string $expected_content_type, Request $request, string $message = ''): void {
    $response = $this->handleRequest($request);

    $this->assertEquals($expected_code, $response->getStatusCode(), $message);
    $this->assertEquals($expected_content, $response->getContent(), $message);
    $this->assertEquals($expected_content_type, $response->headers->get('Content-Type'), $message);
  }

  /**
   * Handles a request and gets the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Incoming request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  protected function handleRequest(Request $request): Response {
    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::service('http_kernel');
    return $kernel->handle($request);
  }

  /**
   * Loads the file currently attached to the media.
   *
   * This can be different from $this->file, if the media has been updated.
   *
   * @return \Drupal\file\FileInterface|null
   *   File entity.
   */
  protected function loadCurrentMediaFile(): ?FileInterface {
    $media = Media::load($this->media->id());
    $fid = $media->getSource()->getSourceFieldValue($media);
    $this->assertNotNull($fid);
    return File::load($fid);
  }

}
