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
      foreach ($replacements as $key => $value) {
        $this->assertStringContainsString($key, $message, $assertion_message);
        $this->assertArrayHasKey($key, $record['context'], $assertion_message);
        $this->assertEquals($value, $record['context'][$key], $assertion_message);
      }
    }
  }

  /**
   * Skips log messages that are not relevant to the current test.
   *
   * @param int|null $level
   *   Only skip messages with the given level.
   * @param string|null $channel
   *   Only skip messages with the given channel.
   * @param string|null $message
   *   Only skip messages with the given message.
   * @param string|null $regex
   *   Only skip messages where the message matches a regular expression.
   * @param array $context
   *   Only skip message with specific context values.
   */
  protected function skipLogMessages(
    ?int $level = NULL,
    ?string $channel = NULL,
    ?string $message = NULL,
    ?string $regex = NULL,
    array $context = [],
  ): void {
    while ($record = reset($this->logger->records)) {
      if ($level !== NULL && $record['level'] != $level) {
        break;
      }
      if ($channel !== NULL && ($record['context']['channel'] ?? NULL) !== $channel) {
        break;
      }
      if ($message !== NULL && $record['message'] !== $message) {
        break;
      }
      if ($regex !== NULL && !preg_match($regex, $record['message'])) {
        break;
      }
      foreach ($context as $key => $value) {
        if ($record['context'][$key] ?? NULL !== $value) {
          break;
        }
      }
      array_shift($this->logger->records);
    }
  }

  /**
   * Asserts that the log does not have any further messages.
   */
  protected function assertNoFurtherLogMessages(string $message = ''): void {
    if (!$this->logger->records) {
      $this->addToAssertionCount(1);
      return;
    }
    $record = reset($this->logger->records);
    unset($record['context']['backtrace']);
    if ($message !== '') {
      $message .= "\n";
    }
    $this->assertNull($record, $message . 'No further log records expected.');
  }

}
