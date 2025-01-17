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

/**
 * Creates a WOPI discovery value object.
 */
interface DiscoveryLoaderInterface {

  /**
   * Gets a discovery value object.
   *
   * @return \Drupal\collabora_online\Discovery\CollaboraDiscoveryInterface
   *   Discovery value object.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   Fetching the discovery.xml failed, or the result is not valid xml.
   */
  public function getDiscovery(): CollaboraDiscoveryInterface;

}
