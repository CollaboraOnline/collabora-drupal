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

namespace Drupal\collabora_online\Discovery;

use Drupal\collabora_online\Util\ExpireTimeHelper;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Service to load the discovery.xml from the Collabora server.
 */
class CachingDiscoveryFetcher implements CollaboraDiscoveryFetcherInterface {

  protected const DEFAULT_CID = 'collabora_online.discovery';

  public function __construct(
    #[AutowireDecorated]
    protected readonly CollaboraDiscoveryFetcherInterface $decorated,
    #[Autowire(service: 'cache.default')]
    protected readonly CacheBackendInterface $cache,
    protected readonly TimeInterface $time,
    protected readonly string $cid = self::DEFAULT_CID,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDiscoveryXml(RefinableCacheableDependencyInterface $cacheability): string {
    $cached = $this->cache->get($this->cid);
    if ($cached) {
      $cacheability->addCacheTags($cached->tags);
      ExpireTimeHelper::mergeExpireTimestamp($cacheability, (int) $cached->expire, $this->time);
      return $cached->data;
    }
    // In theory, the $cacheability could already contain unrelated cache
    // metadata when this method is called. We need to make sure that these do
    // not leak into the cache.
    $local_cacheability = new CacheableMetadata();
    $xml = $this->decorated->getDiscoveryXml($local_cacheability);

    $cacheability->addCacheableDependency($local_cacheability);

    $this->cache->set(
      $this->cid,
      $xml,
      ExpireTimeHelper::getExpireTimestamp($local_cacheability, $this->time),
      $local_cacheability->getCacheTags(),
    );
    return $xml;
  }

}
