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

use Drupal\collabora_online\Util\DateTimeHelper;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\collabora_online\Util\DateTimeHelper
 */
class DateTimeHelperTest extends UnitTestCase {

  /**
   * @covers ::format
   *
   * @testWith ["Europe/Brussels"]
   *           ["Australia/Melbourne"]
   */
  public function testFormat(string $timezone): void {
    // Verify that the result is timezone-independent.
    date_default_timezone_set($timezone);
    $timestamp = 1736969181;
    $this->assertSame(
      '2025-01-15T19:26:21+00:00',
      DateTimeHelper::format($timestamp),
    );
    $this->assertSame(
      '19:26:21',
      DateTimeHelper::format($timestamp, 'H:i:s'),
    );
  }

}
