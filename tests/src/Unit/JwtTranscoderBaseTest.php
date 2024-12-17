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

namespace Drupal\Tests\collabora_online\Unit;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\collabora_online\Jwt\JwtTranscoderBase;
use Drupal\collabora_online\Jwt\JwtTranscoderInterface;
use Drupal\Tests\UnitTestCase;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;

/**
 * Tests the transcoder functionality of the base class.
 *
 * @coversDefaultClass \Drupal\collabora_online\Jwt\JwtTranscoderBase
 */
class JwtTranscoderBaseTest extends UnitTestCase {

  /**
   * Tests encode and decode.
   */
  public function testTranscode(): void {
    $logger = new TestLogger();
    $transcoder = $this->createTranscoder($logger);
    // Set the expire timestamp far enough in the future.
    $expire_timestamp = gettimeofday(TRUE) + 55555;
    $token = $transcoder->encode(['a' => 'A'], $expire_timestamp);
    $payload = $transcoder->decode($token);
    $expected_payload = [
      'a' => 'A',
      'exp' => $expire_timestamp,
    ];
    $this->assertSame($expected_payload, $payload);
    $this->assertSame(
      $expected_payload,
      (array) json_decode(JWT::urlsafeB64Decode(
        explode('.', $token)[1],
      )),
      'The token body part should contain the data.',
    );
    $this->assertSame([], $logger->records);
  }

  /**
   * Tests behavior on an expired token.
   */
  public function testExpiredToken(): void {
    $logger = new TestLogger();
    $transcoder = $this->createTranscoder($logger);
    // Set the expire timestamp to be in the past.
    $expire_timestamp = gettimeofday(TRUE) - 10;
    $token = $transcoder->encode(['a' => 'A'], $expire_timestamp);
    $payload = $transcoder->decode($token);
    $this->assertNull($payload);
    $this->assertSame(
      [
        'a' => 'A',
        'exp' => $expire_timestamp,
      ],
      (array) json_decode(JWT::urlsafeB64Decode(
        explode('.', $token)[1],
      )),
      'The token body part should contain the data.',
    );
    $this->assertSame(
      [
        [
          'level' => 'error',
          'message' => 'Expired token',
          'context' => [],
        ],
      ],
      $logger->records,
    );
  }

  /**
   * Tests behavior on a malformed token.
   */
  public function testDecodeMalformedToken(): void {
    $logger = new TestLogger();
    $transcoder = $this->createTranscoder($logger);
    // Set a token with an invalid format.
    $token = 'aa.bb.cc';
    $payload = $transcoder->decode($token);
    $this->assertNull($payload);
    $this->assertSame(
      [
        [
          'level' => 'error',
          'message' => 'Syntax error, malformed JSON',
          'context' => [],
        ],
      ],
      $logger->records,
    );
  }

  /**
   * Tests behavior on a bad token signature.
   */
  public function testBadSignature(): void {
    $logger = new TestLogger();
    $transcoder = $this->createTranscoder($logger);
    $expire_timestamp = gettimeofday(TRUE) + 55555;
    $token = $transcoder->encode(['a' => 'A'], $expire_timestamp);
    // Change one character in the signature part.
    $token[-2] = ($token[-2] !== 'x') ? 'x' : 'y';
    $payload = $transcoder->decode($token);
    $this->assertNull($payload);
    $this->assertSame(
      [
        [
          'level' => 'error',
          'message' => 'Signature verification failed',
          'context' => [],
        ],
      ],
      $logger->records,
    );
  }

  /**
   * Creates a transcoder for testing, with a fixed key.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger.
   *
   * @return \Drupal\collabora_online\Jwt\JwtTranscoderInterface
   *   A new transcoder instance.
   */
  protected function createTranscoder(LoggerInterface $logger): JwtTranscoderInterface {
    return new class ($logger) extends JwtTranscoderBase {

      /**
       * {@inheritdoc}
       */
      protected function getKey(): string {
        return 'ahhZ2/C1RiWG9OYjLPdkj/6SoRjFIgunF/r075TvexpsZPby2CP2zUpd4eevSG71hd46r3Zl3bT0emsMO4/PNw==';
      }

    };
  }

}
