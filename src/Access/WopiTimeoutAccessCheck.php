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

use Drupal\collabora_online\Util\DotNetTime;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access checker to deny expired requests based on X-WOPI-Timestamp.
 *
 * Note that the X-WOPI-Timestamp is in DotNet ticks.
 */
class WopiTimeoutAccessCheck implements AccessInterface {

  public function __construct(
    protected readonly TimeInterface $time,
    protected readonly ConfigFactoryInterface $configFactory,
    // The recommended TTL is 20 minutes.
    protected readonly int $ttlSeconds = 20 * 60,
  ) {}

  /**
   * Checks if the X-WOPI-Timestamp is expired.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Request $request): AccessResultInterface {
    $config = $this->configFactory->get('collabora_online.settings');
    if (!($config->get('cool')['wopi_proof'] ?? TRUE)) {
      return AccessResult::allowed()
        ->addCacheableDependency($config);
    }
    // Each incoming request will have a different timestamp, so there is no
    // point in caching.
    return $this->doCheckAccess($request)
      ->setCacheMaxAge(0);
  }

  /**
   * Checks if the X-WOPI-Timestamp is expired, without cache metadata.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function doCheckAccess(Request $request): AccessResult {
    $wopi_ticks_str = $request->headers->get('X-WOPI-Timestamp', '');
    // Unfortunately, is_numeric() confuses the IDE's static analysis, so use
    // regular expression instead.
    if (!preg_match('#^[1-9]\d+$#', $wopi_ticks_str)) {
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

}
