<?php

declare(strict_types=1);

/*
 * Copyright the Collabora Online contributors.
 *
 * SPDX-License-Identifier: MPL-2.0
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

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
      '#default_value' => $cool_settings['server'] ?? '',
      '#required' => TRUE,
    ];

    $form['wopi_base'] = [
      '#type' => 'url',
      '#title' => $this->t('WOPI host URL'),
      '#description' => $this->t('Likely https://&lt;drupal_server&gt;'),
      '#default_value' => $cool_settings['wopi_base'] ?? '',
      '#required' => TRUE,
    ];

    $form['key_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JWT private key ID'),
      '#default_value' => $cool_settings['key_id'] ?? '',
      '#required' => TRUE,
    ];

    $form['access_token_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Access Token Expiration (in seconds)'),
      '#default_value' => $cool_settings['access_token_ttl'] ?? 0,
      '#min' => 0,
      '#required' => TRUE,
    ];

    $form['disable_cert_check'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable TLS certificate check for COOL.'),
      '#default_value' => $cool_settings['disable_cert_check'] ?? FALSE,
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Remove slashes at the end of wopi_base URL.
    $wopi_base = rtrim($form_state->getValue('wopi_base'), '/');
    $form_state->setValueForElement($form['wopi_base'], $wopi_base);

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(static::SETTINGS)
      ->set('cool.server', $form_state->getValue('server'))
      ->set('cool.wopi_base', $form_state->getValue('wopi_base'))
      ->set('cool.key_id', $form_state->getValue('key_id'))
      ->set('cool.access_token_ttl', $form_state->getValue('access_token_ttl'))
      ->set('cool.disable_cert_check', $form_state->getValue('disable_cert_check'))
      ->set('cool.allowfullscreen', $form_state->getValue('allowfullscreen'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
