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

namespace Drupal\Tests\collabora_online\Traits;

use ColinODell\PsrTestLogger\TestLogger;

/**
 * Adds a test logger service.
 */
trait KernelTestLoggerTrait {

  /**
   * The test logger channel.
   *
   * @var \ColinODell\PsrTestLogger\TestLogger
   */
  protected TestLogger $logger;

  /**
   * Registers a test logger.
   *
   * This needs to be called from setUp().
   */
  protected function setUpLogger(): void {
    $this->logger = new TestLogger();
    \Drupal::service('logger.factory')->addLogger($this->logger);
  }

  /**
   * Asserts that the next log message is as expected.
   *
   * The respective message is removed in the process.
   * However, it is not removed in recordsByLevel.
   *
   * @param int|null $level
   *   Expected log level, or NULL to accept any level.
   * @param string|null $message
   *   Expected log message, or NULL to accept any message.
   * @param array $replacements
   *   Text replacements expected in $record['context'].
   * @param string|null $channel
   *   Expected logger channel name, or NULL to accept any.
   * @param int $position
   *   Expected position of the log message in remaining records.
   *   By default, the first remaining record is picked.
   *   Put a negative number to pick the nth last record.
   * @param string $assertion_message
   *   Message to show with the assertion.
   */
  protected function assertLogMessage(
    ?int $level = NULL,
    ?string $message = NULL,
    array $replacements = [],
    ?string $channel = 'cool',
    int $position = 0,
    string $assertion_message = '',
  ): void {
    $assertion_message_prefix = $assertion_message;
    if ($assertion_message !== '') {
      $assertion_message_prefix .= "\n";
    }
    // Pick and remove the element at the given position.
    $record = array_splice($this->logger->records, $position, 1)[0] ?? NULL;
    if ($record === NULL) {
      $this->fail($assertion_message_prefix . 'No log messages left.');
    }
    if ($message !== NULL) {
      $this->assertSame($message, $record['message'], $assertion_message);
    }
    if ($level !== NULL) {
      $this->assertSame($level, $record['level'], $assertion_message);
    }
    if ($channel !== NULL) {
      $this->assertSame($channel, $record['context']['channel'], $assertion_message);
    }
    // Catch typos in the placeholder keys.
    // This could go undetected, if the translatable string and the placeholders
    // are copied from production code into the test code.
    if ($message !== NULL) {
      foreach (array_keys($replacements) as $placeholder) {
        $this->assertStringContainsString($placeholder, $message, $assertion_message);
      }
    }
    $this->assertSame(
      $replacements,
      array_intersect_key($replacements, $record['context']),
      $assertion_message,
    );
  }

  /**
   * Asserts that the log does not have any further messages.
   */
  protected function assertNoFurtherLogMessages(string $message = ''): void {
    if ($message !== '') {
      $message .= "\n";
    }
    $this->assertSame([], $this->logger->records, $message . 'No further log records expected.');
  }

}
