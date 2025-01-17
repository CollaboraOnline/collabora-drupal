<?php

declare(strict_types=1);

namespace Drupal\collabora_online\Util;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;

/**
 * Static methods to convert expire timestamp vs cache max-age.
 *
 * @see \Drupal\Core\Cache\VariationCache::maxAgeToExpire()
 */
class ExpireTimeHelper {

  /**
   * Reads the max-age and returns it as an expire timestamp.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $cacheability
   *   Cache metadata object to read from.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   *
   * @return int
   *   Expire timestamp.
   */
  public static function getExpireTimestamp(CacheableDependencyInterface $cacheability, TimeInterface $time): int {
    $max_age = $cacheability->getCacheMaxAge();
    if ($max_age === Cache::PERMANENT) {
      return Cache::PERMANENT;
    }
    return $max_age + $time->getRequestTime();
  }

  /**
   * Merges an expire timestamp into the max-age of a cacheable object.
   *
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Cache metadata object to write into.
   * @param int $expire
   *   Expire timestamp.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public static function mergeExpireTimestamp(RefinableCacheableDependencyInterface $cacheability, int $expire, TimeInterface $time): void {
    $max_age = ($expire === Cache::PERMANENT)
      ? Cache::PERMANENT
      : $expire - $time->getRequestTime();
    $cacheability->mergeCacheMaxAge($max_age);
  }

}
