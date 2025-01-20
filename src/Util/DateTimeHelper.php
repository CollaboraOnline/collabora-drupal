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

namespace Drupal\collabora_online\Util;

/**
 * Static methods related to date and time.
 */
class DateTimeHelper {

  /**
   * Formats a timestamp as a date string in UTC.
   *
   * @param int $timestamp
   *   Unix timestamp.
   * @param string $format
   *   Date format.
   *   The default format 'c' is ISO 8601, which is used in WOPI responses.
   *
   * @return string
   *   Formatted date string in the given format, in UTC.
   */
  public static function format(int $timestamp, string $format = 'c'): string {
    // If the input is in timestamp format, the timezone is always UTC.
    return \DateTimeImmutable::createFromFormat('U', (string) $timestamp)
      ->format($format);
  }

}
