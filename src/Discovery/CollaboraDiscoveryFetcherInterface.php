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
 * Service to load the discovery.xml from the Collabora server.
 */
interface CollaboraDiscoveryFetcherInterface {

  /**
   * Gets the contents of discovery.xml from the Collabora server.
   *
   * @return string
   *   The full contents of discovery.xml.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  public function getDiscoveryXml(): string;

}
