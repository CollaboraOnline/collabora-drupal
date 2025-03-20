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

namespace Drupal\collabora_online\Access;

use Drupal\collabora_online\Discovery\DiscoveryFetcherInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\collabora_online\Util\DotNetTime;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Utility\Error;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PublicKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access checker to verify the WOPI token.
 *
 * This does not check the X-WOPI-Timestamp expiration.
 *
 * This is inspired by the wopi-lib package, see
 * https://github.com/Champs-Libres/wopi-lib/blob/master/src/Service/ProofValidator.php.
 */
class WopiProofAccessCheck implements AccessInterface {

  public function __construct(
    protected readonly DiscoveryFetcherInterface $discoveryFetcher,
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TimeInterface $time,
    // The recommended TTL is 20 minutes.
    protected readonly int $ttlSeconds = 20 * 60,
  ) {}

  /**
   * Checks if the request has a WOPI proof.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Request $request): AccessResultInterface {
    $config = $this->configFactory->get('collabora_online.settings');
    if (!($config->get('cool.wopi_proof') ?? TRUE)) {
      return AccessResult::allowed()
        ->addCacheableDependency($config);
    }
    // Each incoming request will have a different proof and timestamp, so there
    // is no point in caching.
    return $this->doCheckAccess($request)
      ->setCacheMaxAge(0);
  }

  /**
   * Checks the WOPI proof and the timeout, without adding cache metadata.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   An access result without cache metadata.
   *   Instead, calling code should set cache max age 0.
   */
  protected function doCheckAccess(Request $request): AccessResult {
    $timeout_access = $this->checkTimeout($request);
    if (!$timeout_access->isAllowed()) {
      return $timeout_access;
    }
    // There is no need for ->andIf(), because there is no cache metadata to
    // merge.
    return $this->checkProof($request);
  }

  /**
   * Checks if the X-WOPI-Timestamp is expired, without cache metadata.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   An access result without cache metadata.
   */
  protected function checkTimeout(Request $request): AccessResult {
    $wopi_ticks_str = $request->headers->get('X-WOPI-Timestamp', '');
    if (!is_numeric($wopi_ticks_str)) {
      return AccessResult::forbidden('The X-WOPI-Timestamp header is missing, empty or invalid.');
    }
    $wopi_timestamp = DotNetTime::ticksToTimestamp((float) $wopi_ticks_str);
    $now_timestamp = $this->time->getRequestTime();
    $wopi_age_seconds = $now_timestamp - $wopi_timestamp;
    if ($wopi_age_seconds > $this->ttlSeconds) {
      return AccessResult::forbidden(sprintf(
        'The X-WOPI-Timestamp header is %s seconds old, which is more than the %s seconds TTL.',
        $wopi_age_seconds,
        $this->ttlSeconds,
      ));
    }
    return AccessResult::allowed();
  }

  /**
   * Checks the WOPI proof, without adding cache metadata.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   An access result without cache metadata.
   */
  protected function checkProof(Request $request): AccessResult {
    try {
      $keys = $this->getKeys();
    }
    catch (CollaboraNotAvailableException $e) {
      $log_message = "Failure in WOPI proof check:<br>\n"
        . Error::DEFAULT_ERROR_MESSAGE;
      $log_args = Error::decodeException($e);
      $this->logger->error($log_message, $log_args);
      return AccessResult::forbidden('Cannot get discovery for proof keys.');
    }
    if (!isset($keys['current'])) {
      return AccessResult::forbidden('Missing or incomplete WOPI proof keys.');
    }
    $signatures = $this->getSignatures($request);
    if (!isset($signatures['current'])) {
      return AccessResult::forbidden('Missing or incomplete WOPI proof headers.');
    }
    $subject = $this->getSubject($request);

    // Try different key and signature combinations.
    foreach ($keys as $key_name => $key) {
      foreach ($signatures as $signature_name => $signature) {
        if ($key_name === 'old' && $signature_name === 'old') {
          // Don't verify an old signature with an old key.
          continue;
        }
        $success = $key->verify($subject, $signature);
        if ($success) {
          return AccessResult::allowed();
        }
      }
    }
    return AccessResult::forbidden('WOPI proof mismatch.');
  }

  /**
   * Gets the message to be signed.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The message to be signed.
   */
  protected function getSubject(Request $request): string {
    // This class is not responsible for checking the expiration, but it still
    // needs the WOPI timestamp to build the message for the signature.
    $timestamp_ticks = $request->headers->get('X-WOPI-Timestamp');
    $token = (string) $request->query->get('access_token', '');
    $url = $request->getUri();
    return sprintf(
      '%s%s%s%s%s%s',
      pack('N', strlen($token)),
      $token,
      pack('N', strlen($url)),
      strtoupper($url),
      pack('N', 8),
      pack('J', $timestamp_ticks),
    );
  }

  /**
   * Gets RSA public keys from the discovery.xml.
   *
   * The discovery.xml has a current and an old key.
   * This is to support situations when the key has been recently changed, but
   * the incoming request was signed with the older key.
   *
   * @return array<'current'|'old', \phpseclib3\Crypt\RSA\PublicKey>
   *   Current and old public key, or just the current if they are the same, or
   *   empty array if none found.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The discovery cannot be loaded.
   */
  protected function getKeys(): array {
    $discovery = $this->discoveryFetcher->getDiscovery();
    // Get current and old key.
    // Remove empty values.
    // If both are the same, keep only the current one.
    $public_keys = array_unique(array_filter([
      'current' => $discovery->getProofKey(),
      'old' => $discovery->getProofKeyOld(),
    ]));
    $key_objects = [];
    foreach ($public_keys as $key_name => $key_str) {
      $key_obj = $this->prepareKey($key_str, $key_name);
      if ($key_obj === NULL) {
        continue;
      }
      $key_objects[$key_name] = $key_obj;
    }
    return $key_objects;
  }

  /**
   * Gets an RSA key object based on a string value.
   *
   * @param string $key_str
   *   Key string value from discovery.xml.
   * @param 'current'|'old' $key_name
   *   Key name, only used for logging.
   *
   * @return \phpseclib3\Crypt\RSA\PublicKey|null
   *   An RSA public key object, or NULL on failure.
   */
  protected function prepareKey(string $key_str, string $key_name): ?PublicKey {
    try {
      $key_object = PublicKeyLoader::loadPublicKey($key_str);
    }
    catch (\Throwable $e) {
      $log_message = "Problem with the @name key from discovery.yml:<br>\n"
        . Error::DEFAULT_ERROR_MESSAGE;
      $log_args = Error::decodeException($e);
      $log_args['@name'] = $key_name;
      $this->logger->error($log_message, $log_args);
      return NULL;
    }
    if (!$key_object instanceof PublicKey) {
      $log_message = "Problem with the @name key from discovery.yml:<br>\n"
        . "Expected RSA public key, found @type.";
      $log_args = [
        '@name' => $key_name,
        '@type' => get_debug_type($key_object),
      ];
      $this->logger->error($log_message, $log_args);
      return NULL;
    }
    return $key_object
      ->withHash('sha256')
      ->withPadding(RSA::SIGNATURE_RELAXED_PKCS1);
  }

  /**
   * Gets the current and old signature from the request.
   *
   * The request will have a current and an old signature.
   * This is to support situations when the key has been recently changed, but
   * the cached discovery.xml still has the old key.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Incoming request that may have a signature to be verified.
   *
   * @return array{current?: string, old?: string}
   *   Current and old signature from the request, decoded and ready for use.
   *   If they are the same, only one of them is returned.
   *   If no signatures are found, an empty array is returned.
   */
  protected function getSignatures(Request $request): array {
    // Get the current and old proof header.
    // Remove empty values.
    // If both are the same, keep only the current one.
    $proof_headers = array_unique(array_filter([
      'current' => $request->headers->get('X-WOPI-Proof'),
      'old' => $request->headers->get('X-WOPI-ProofOld'),
    ]));
    $decoded_proof_headers = array_map(
      fn (string $header_value) => base64_decode($header_value, TRUE),
      $proof_headers,
    );
    // Remove false values where decoding failed.
    return array_filter($decoded_proof_headers);
  }

}
