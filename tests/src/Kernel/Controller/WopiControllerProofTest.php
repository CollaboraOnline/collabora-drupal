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

namespace Drupal\Tests\collabora_online\Kernel\Controller;

use Drupal\collabora_online\Discovery\DiscoveryFetcherInterface;
use Drupal\collabora_online\Discovery\DiscoveryInterface;
use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\collabora_online\Util\DotNetTime;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;
use Drupal\Tests\collabora_online\Traits\KernelTestLoggerTrait;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests proof validation on WOPI requests.
 *
 * @covers \Drupal\collabora_online\Access\WopiProofAccessCheck
 */
class WopiControllerProofTest extends WopiControllerTestBase {

  use KernelTestLoggerTrait;

  protected const PROOF_KEY = 'BgIAAACkAABSU0ExAAgAAAEAAQCxJYuefceQ4XI3/iUQvL9+ebLFZSRdM1n6fkB+OtILJexHUsD/aItTWgzB/G6brdxLlyHXoPjbJl4QoWZVrr1XY+ZHQ/a9Yzf/VN2mPLKFB9hmnUPI580VpFfkC3gCgpqwFwMpAkQSzYSDFQ/7W4ryPP6irvVzhg16IqQ9oEhZWmwy6caKcqh4BK31oI8SrI6bsZLBMTli70197UWHmgIGk4JJbeC8cBFb6uZDaidAcRn1HSAF2JnaEscUNMIsiNMM/71BT6U6hVSv5Qk0oISMLfVOeCPQZ6OmYo4M42wDKBpaJGMOpgoeQX6Feq+agf7uBvd8S/ITGZ8WinQfHZaQ';

  /**
   * Another key that has the correct format but where the proofs don't match.
   */
  protected const BAD_PROOF_KEY = 'BgIAAACkAABSU0ExAAgAAAEAAQDj9QjZQ9bOOw5LfAMxMLMDTLgHsNvBcdRpYQ8S9qK9ylJevgp+j66k9/uyKXSwI9WTVHW+XLTCPq6aId+XqB5e8+H5rov7e4Itkpnr6eXZ1jAu9TW2jEnqCYdGqG6Pv0kbRv1gUFEsjciy8i9UAQ12Ons7J58nQLd3tJ4WATANoCyVJLfA7BQ6IRSq8/K3jqmSE8xu3HDLX+lnMrsK2KL4lYcjerGZpmOKI5tPZbC5xSMkB9alE5NhTYeYw25CyG4FHoss2AwNgvSQDaf6d/icNg5ZoGQwtISGKL6IFc4oogFHFdvR4FQCQ61wdz7RmHjJUpsPFio8htuSeMjbC7fS';

  /**
   * A proof value that has the correct format but matches nothing.
   */
  protected const BAD_PROOF = 'Uylo0jiFS0q2OGvwC2W3iLKQ/4kklknUyrZ18fHfwJ7BN8Ds79whfauFqP9fHskUGOu0MZPoRKftktsCd9/T2Dpi1v/xg+ZNUSFOemoEWtreLK0CIyjEzJHf9u2peQmcra83MzyUlbzI94/2GTuB36kkthAyLyz5cgXQnSWLIRpf0K207dZqX6aWUZ5MCvoK8tqKtS6IrELccpgGd6bJvb3XDRjM1LzPIZVh+TcEEeNQiKLicJw21Nfnw64F4ICnVl2e3Eevb2QeQPm6BrG/hMcfU2Yx5pqlTHXJedSqIdLodLecCg3hsiQaNf0YkAffR2LNb5+GiPVC+D+YMpv6ZQ==';

  /**
   * Mock return value for $discovery->getProofKey().
   *
   * @var string
   */
  protected string $wopiProofKey = self::PROOF_KEY;

  /**
   * Mock return value for $discovery->getProofKeyOld().
   *
   * @var string
   */
  protected string $wopiProofKeyOld = self::PROOF_KEY;

  /**
   * Callback to replace CollaboraDiscoveryFetcher->getDiscovery().
   *
   * @var \Closure(): \Drupal\collabora_online\Discovery\DiscoveryInterface
   */
  protected \Closure $mockGetDiscovery;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set a mock discovery with custom proof keys.
    $mock_discovery = $this->createMock(DiscoveryInterface::class);
    $mock_discovery->method('getProofKey')->willReturnReference($this->wopiProofKey);
    $mock_discovery->method('getProofKeyOld')->willReturnReference($this->wopiProofKeyOld);
    $this->mockGetDiscovery = fn () => $mock_discovery;

    $mock_discovery_fetcher = $this->createMock(DiscoveryFetcherInterface::class);
    $mock_discovery_fetcher->method('getDiscovery')->willReturnCallback(
      fn () => ($this->mockGetDiscovery)(),
    );
    $this->container->set(DiscoveryFetcherInterface::class, $mock_discovery_fetcher);
  }

  /**
   * Tests access check based on WOPI proof and timestamp.
   */
  public function testWopiProofAccess(): void {
    $requests = $this->createRequests();
    // The values below have been recorded from real WOPI requests coming from
    // a Collabora client.
    $wopi_tick_times = [
      'info' => '638701624451393010',
      'file' => '638701624452984064',
      'save' => '638701624725606559',
    ];
    $wopi_proofs = [
      'info' => 'MQSnbW+hu4z18psc+EfHkNBP1oTMTkcTYSOy5bAouB24XKaiaPb/BRF05ds1oq8GIvSjxL0nczIYMOxZyiNzGg3BvkKOm2eGPIMNCJvk7QZyIF/FI6E7uTg4zp7K/x6S+Dph/nVrNT37xjvHsf0MuKjfGdJKDMz8WzhXsKs/yAJVxErrFcabJRuva48fLNmMYkO/c9lLqxpdNvlASBOajeoECuKYwMJitBgMMDwrFuPA7a+RjWIjtYkkHu4oyZXTdWskU6P6LFSE4jWe9rPAxYJsOAXDZpefDYDXUSryazSeK+AgaA3p69ZrFTD5M/FH1hEDKhLqWOll49n7oTnL8w==',
      'file' => 'Ka9G+FAyjzf04Sk2Z1DSX0f0Xk4a9qtUGhKIfF/DPTuJOHykw38XGZy+v045EIsOpVZ6CFnlToI/h6hbstYhBG78O0xMV0L/o65HO8jCkuFPvU4yTXSAfgnKSVuQB3bbti2KnzG57dp6UwwnIUb2kNnCV8W4LTGuCaCBR2Z9Ydwk09UHHaIBo57g6oHGKpLoiMLhOMq4PDW3RBis98Vmc+p6qDipj4f1c7HarBlxew4BLMC0ubRFwXsBxMWMn9E3xJdMdUZOIAyidtghoOSuLbSKyCixsxQ0ONMHRFKq4K//ozoWac+8V8h7IzTbD6LIQwNITJqUW5PwPuzt/dNdTg==',
      'save' => 'Pj5Ak7sBsI4Pa0RVkn+0jJNLhIuGJRKjRi//cv1lHE4O1d3VlgT1WsFc5tRr1IW/OfiLVg6yJielgzSpjNRhushUATq/YdRx4lA61Cx9KQ6Y9hr2SZdg4sNgFjOZSFAfIBBx0P1Hfqgl1olv3EjIO/Fb+7/YNSsSG+rtpjPt8fGdaRxVUa4vCUjVCoJl+uaTY8CohGE4Aj5llXUmL2ZuctA8M/Ts+yOWEPfJ/nTwI0o6oG/2BtrQMQxChM7Lk59W+iGHh/AbxwRU+K0t7bdktzwtYbRmWarJwSIE/7pZ0zVbVj92hFNqtqKzR52+ACTqLB/qQnpSMl3Yu3Z5FkcS9g==',
    ];
    foreach ($requests as $name => $request) {
      $wopi_tick_time = $wopi_tick_times[$name];
      $wopi_proof = $wopi_proofs[$name];
      $this->doTestWopiProofAccess($request, $wopi_tick_time, $wopi_proof, $name);
    }
  }

  /**
   * Tests a single WOPI route with different proof combinations.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A basic request for the route.
   * @param string $wopi_tick_time
   *   Proof time in DotNet ticks, as for the X-WOPI-Timestamp header.
   * @param string $wopi_proof
   *   WOPI proof value, as for the X-WOPI-Proof header.
   * @param string $message
   *   Message to use in assertions.
   */
  protected function doTestWopiProofAccess(Request $request, string $wopi_tick_time, string $wopi_proof, string $message): void {
    $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJmaWQiOiIxMDAwIiwidWlkIjoiMSIsIndyaSI6dHJ1ZSwiZXhwIjoxNzM0NjUyMDQ1LjA0NjY3NX0.tM4aHamo7sQA3pkqiNbABlMM5mi-Z9vODaSm847hxIA';
    $request->query->set('access_token', $token);
    $request->query->set('access_token_ttl', '0');
    $request->server->set('QUERY_STRING', http_build_query($request->query->all()));
    // The test proof values were generated in an environment with
    // 'web.test:8080' as the host name.
    $request->headers->set('HOST', 'web.test:8080');
    $request->headers->set('X-WOPI-Timestamp', $wopi_tick_time);
    $set_state = function (
      ?int $offset_seconds = NULL,
      ?string $proof_recent = NULL,
      ?string $proof_old = NULL,
      ?string $key_recent = NULL,
      ?string $key_old = NULL,
    ) use ($request, $wopi_tick_time, $wopi_proof) {
      $this->assertNoFurtherLogMessages();
      // By default, set a fake request time 18 minutes after the WOPI time.
      $_SERVER['REQUEST_TIME'] = DotNetTime::ticksToTimestamp((float) $wopi_tick_time)
        + ($offset_seconds ?? 18 * 20);
      $request->headers->set('X-WOPI-Proof', $proof_recent ?? $wopi_proof);
      $request->headers->set('X-WOPI-ProofOld', $proof_old ?? $wopi_proof);
      $this->wopiProofKey = $key_recent ?? self::PROOF_KEY;
      $this->wopiProofKeyOld = $key_old ?? self::PROOF_KEY;
      // Set a timestamp that is earlier than the timeout.
      // For now there is no parameter to manipulate this, but it is still part
      // of the "state", so it makes sense to setup in this function.
      JWT::$timestamp = 1734605245;
    };

    // With all values at their default, access is granted.
    $set_state();
    // Clone the request to avoid side effects.
    $this->assertRequestSuccess(clone $request, $message);

    // A single bad proof is ok, if the old proof is valid.
    $set_state(proof_recent: self::BAD_PROOF);
    $this->assertRequestSuccess(clone $request, $message);

    // A single bad old proof is ok, if the recent proof is valid.
    $set_state(proof_old: self::BAD_PROOF);
    $this->assertRequestSuccess(clone $request, $message);

    // Two bad proofs lead to failure.
    $set_state(proof_recent: self::BAD_PROOF, proof_old: self::BAD_PROOF);
    $this->assertAccessDeniedResponse('WOPI proof mismatch.', clone $request, $message);

    // A non-matching recent proof key is ok, if the old key still matches the
    // recent proof from the request.
    $set_state(key_recent: self::BAD_PROOF_KEY);
    $this->assertRequestSuccess(clone $request, $message);

    // Two non-matching proof keys lead to failure.
    $set_state(key_recent: self::BAD_PROOF_KEY, key_old: self::BAD_PROOF_KEY);
    $this->assertAccessDeniedResponse('WOPI proof mismatch.', clone $request, $message);

    // The old proof matching the old key does not work.
    $set_state(proof_recent: self::BAD_PROOF, key_recent: self::BAD_PROOF_KEY);
    $this->assertAccessDeniedResponse('WOPI proof mismatch.', clone $request, $message);

    // Set a fake request time 22 minutes after the WOPI time.
    $set_state(offset_seconds: 22 * 60);
    $this->assertAccessDeniedResponse('The X-WOPI-Timestamp header is 1320 seconds old, which is more than the 1200 seconds TTL.', clone $request, $message);

    // Test behavior when the discovery throws an exception.
    $set_state();
    $mock_discovery_backup = $this->mockGetDiscovery;
    $this->mockGetDiscovery = fn () => throw new CollaboraNotAvailableException(
      'The discovery.xml cannot be loaded or is malformed.',
    );
    $this->assertAccessDeniedResponse('Cannot get discovery for proof keys.', clone $request, $message);
    $this->mockGetDiscovery = $mock_discovery_backup;
    $this->assertLogMessage(
      RfcLogLevel::ERROR,
      "Failure in WOPI proof check:<br>\n" . Error::DEFAULT_ERROR_MESSAGE,
      [
        '%type' => CollaboraNotAvailableException::class,
        '@message' => 'The discovery.xml cannot be loaded or is malformed.',
      ],
    );

    $set_state();
  }

  /**
   * Asserts that a request results in a successful response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $message
   *   A message to distinguish from other assertions.
   */
  protected function assertRequestSuccess(Request $request, string $message): void {
    $response = $this->handleRequest($request);
    $this->assertEquals(
      Response::HTTP_OK,
      $response->getStatusCode(),
      // Print the failure message if this is not 200.
      $message . "\n" . substr((string) $response->getContent(), 0, 3000),
    );
    // Ignore log message from write requests.
    $this->skipLogMessages(
      RfcLogLevel::INFO,
      'cool',
      regex: '@Media entity .* was updated with Collabora\.|The file contents for media .* were overwritten with Collabora\.@',
    );
    $this->assertNoFurtherLogMessages();
  }

}
