<?php

declare(strict_types=1);

namespace Drupal\collabora_online\Time;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;

/**
 * Service to convert between expire timestamp and cache max-age value.
 */
class ExpireTimestampConverter {

  public function __construct(
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Gets an expire timestamp from a max-age value.
   *
   * @param int $max_age
   *   Max-age value.
   *
   * @return int
   *   Expire timestamp.
   */
  public function getExpireTimestamp(int $max_age): int {
    return ($max_age === Cache::PERMANENT)
      ? Cache::PERMANENT
      : $max_age + $this->time->getRequestTime();
  }

  /**
   * Gets a max-age value from an expire timestamp.
   *
   * @param int $expire
   *   Expire timestamp.
   *
   * @return int
   *   Max-age value.
   */
  public function getMaxAge(int $expire): int {
    return ($expire === Cache::PERMANENT)
      ? Cache::PERMANENT
      : $expire - $this->time->getRequestTime();
  }

}
