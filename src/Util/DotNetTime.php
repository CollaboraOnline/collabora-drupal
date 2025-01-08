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
 * Contains static methods to convert DotNet ticks to and from UNIX timestamp.
 *
 * All DotNet tick values are assumed to be in UTC timezone.
 */
class DotNetTime {

  private const MULTIPLIER = 1e7;

  private const OFFSET = 621355968e9;

  /**
   * Converts a DotNet ticks timestamp to a UNIX timestamp.
   *
   * @param int|float $ticks
   *   Time in DotNet ticks in UTC timezone.
   *
   * @return float
   *   Time as UNIX timestamp.
   */
  public static function ticksToTimestamp(int|float $ticks): float {
    return ($ticks - self::OFFSET) / self::MULTIPLIER;
  }

  /**
   * Converts a UNIX timestamp to a DotNet ticks timestamp.
   *
   * @param int|float $timestamp
   *   Time as UNIX timestamp.
   *
   * @return float
   *   Time in DotNet ticks in UTC timezone.
   */
  public static function timestampToTicks(int|float $timestamp): float {
    return $timestamp * self::MULTIPLIER + self::OFFSET;
  }

}
