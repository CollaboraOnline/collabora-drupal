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

namespace Drupal\collabora_online\Exception;

/**
 * Collabora is not available.
 *
 * The reason could be:
 *   - The configuration for this module is empty or invalid.
 *   - The Collabora service is not responding, or is not behaving as expected.
 *
 * This generally points to a problem that will occur for all Collabora
 * operations, independent of specific user input or specific content.
 */
class CollaboraNotAvailableException extends \Exception {}
