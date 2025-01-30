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

namespace Drupal\collabora_online\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to configure module settings for Collabora.
 */
class ConfigForm extends ConfigFormBase {

  const SETTINGS = 'collabora_online.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'collabora_configform';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::SETTINGS);
    $cool_settings = $config->get('cool');

    $form['server'] = [
      '#type' => 'url',
      '#title' => $this->t('Collabora Online server URL'),
      '#description' => $this->t(
        "Base URL for server-side requests from Drupal to Collabora Online.<br>
A trailing slash is optional.<br>
E.g. 'https://collabora.example.com' or 'http://localhost:9980/'.",
      ),
      '#default_value' => $cool_settings['server'] ?? '',
      '#required' => TRUE,
    ];

    $form['discovery_cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Discovery cache TTL'),
      // Microsoft recommends 12-24 hours for this cache.
      '#description' => $this->t(
        "Duration after which the cached discovery.xml needs to be refreshed.<br>
A typical value would be 12 or 24 hours.<br>
A value of 0 effectively disables this cache.<br>
If the proof check is enabled (see below), and Collabora is configured to periodically change the proof keys, then this cache TTL must be shorter than the proof key duration.",
      ),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $cool_settings['discovery_cache_ttl'] ?? 43200,
      // Do not allow a TTL of -1 (forever).
      // The cache needs to be refreshed periodically for the proof key to work.
      '#min' => 0,
      '#required' => TRUE,
    ];

    $form['wopi_base'] = [
      '#type' => 'url',
      '#title' => $this->t('WOPI host URL'),
      '#description' => $this->t(
        "Base URL for server-side WOPI requests from Collabora Online to Drupal.<br>
This can be different from the public Drupal URL, if these requests happen through an internal network.<br>
A trailing slash is optional.<br>
E.g. 'https://drupal.example.com' or 'http://localhost/' or 'http://localhost/subdir'.",
      ),
      '#default_value' => $cool_settings['wopi_base'] ?? '',
      '#required' => TRUE,
    ];

    $form['key_id'] = [
      '#type' => 'key_select',
      '#title' => $this->t('JWT private key'),
      '#default_value' => $cool_settings['key_id'] ?? '',
      '#required' => TRUE,
      '#key_filters' => [
        'type' => ['collabora_jwt_hs'],
      ],
    ];

    $form['access_token_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Access token TTL'),
      '#description' => $this->t('Duration after which the access token for an editing session expires.'),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $cool_settings['access_token_ttl'] ?? 0,
      '#min' => 0,
      '#required' => TRUE,
    ];

    $form['disable_cert_check'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable TLS certificate check for COOL.'),
      '#default_value' => $cool_settings['disable_cert_check'] ?? FALSE,
    ];

    $form['wopi_proof'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify proof header and timestamp in incoming WOPI requests.'),
      '#default_value' => $cool_settings['wopi_proof'] ?? TRUE,
    ];

    $form['allowfullscreen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow COOL to use fullscreen mode.'),
      '#default_value' => $cool_settings['allowfullscreen'] ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(static::SETTINGS)
      ->set('cool.server', $form_state->getValue('server'))
      ->set('cool.discovery_cache_ttl', $form_state->getValue('discovery_cache_ttl'))
      ->set('cool.wopi_base', $form_state->getValue('wopi_base'))
      ->set('cool.key_id', $form_state->getValue('key_id'))
      ->set('cool.access_token_ttl', $form_state->getValue('access_token_ttl'))
      ->set('cool.disable_cert_check', $form_state->getValue('disable_cert_check'))
      ->set('cool.wopi_proof', $form_state->getValue('wopi_proof'))
      ->set('cool.allowfullscreen', $form_state->getValue('allowfullscreen'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
