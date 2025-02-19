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

/**
 * Sets an initial value for the new 'wopi_proof' setting.
 */
function collabora_online_post_update_add_wopi_proof_setting(): void {
  $config = \Drupal::configFactory()->getEditable('collabora_online.settings');
  $cool_settings = $config->get('cool') ?? [];
  $cool_settings['wopi_proof'] ??= TRUE;
  $config->set('cool', $cool_settings);
  $config->save();
}

/**
 * Sets an initial value for the new 'new_file_interval' setting.
 */
function collabora_online_post_update_add_new_file_interval(): void {
  \Drupal::configFactory()->getEditable('collabora_online.settings')
    ->set('cool.new_file_interval', 60)
    ->save();
}

/**
 * Sets an initial value for the new 'discovery_cache_ttl' setting.
 */
function collabora_online_post_update_add_discovery_cache_ttl(): void {
  \Drupal::configFactory()->getEditable('collabora_online.settings')
    ->set('cool.discovery_cache_ttl', 3600)
    ->save();
}
