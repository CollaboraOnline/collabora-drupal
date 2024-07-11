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

namespace Drupal\collabora_online\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ConfigForm extends ConfigFormBase {

    const SETTINGS = 'collabora_online.settings';

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'collabora_configform';
    }

    /**
     * {@inheritdoc}
     */
    public function getEditableConfigNames() {
        return [
            static::SETTINGS
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config(static::SETTINGS);

        $form['server'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Collabora Online server URL'),
            '#default_value' => $config->get('cool')['server'],
            '#required' => TRUE,
        ];

        $form['wopi_base'] = [
            '#type' => 'textfield',
            '#title' => $this->t('WOPI host URL. Likely https://&lt;drupal_server&gt;'),
            '#default_value' => $config->get('cool')['wopi_base'],
            '#required' => TRUE,
        ];

        $form['key_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('JWT private key ID'),
            '#default_value' => $config->get('cool')['key_id'],
            '#required' => TRUE,
        ];

        $form['access_token_ttl'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Access Token Expiration (in seconds)'),
            '#default_value' => $config->get('cool')['access_token_ttl'],
            '#required' => TRUE,
        ];

        $form['disable_cert_check'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Disable TLS certificate check for COOL.'),
            '#default_value' => $config->get('cool')['disable_cert_check'],
        ];

        $form['viewer_role'] = [
            '#type' => 'textfield',
            '#title' => $this->t('The user role for viewers'),
            '#default_value' => $config->get('cool')['viewer_role'],
        ];

        $form['collaborator_role'] = [
            '#type' => 'textfield',
            '#title' => $this->t('The user role for collaborators'),
            '#default_value' => $config->get('cool')['collaborator_role'],
        ];

        $form['administrator_role'] = [
            '#type' => 'textfield',
            '#title' => $this->t('The user role for administrators'),
            '#default_value' => $config->get('cool')['administrator_role'],
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $wopi_base = rtrim($form_state->getValue('wopi_base'), '/');

        $this->config(static::SETTINGS)
            ->set('cool.server', $form_state->getValue('server'))
            ->set('cool.wopi_base', $wopi_base)
            ->set('cool.key_id', $form_state->getValue('key_id'))
            ->set('cool.access_token_ttl', $form_state->getValue('access_token_ttl'))
            ->set('cool.disable_cert_check', $form_state->getValue('disable_cert_check'))
            ->set('cool.viewer_role', $form_state->getValue('viewer_role'))
            ->set('cool.collaborator_role', $form_state->getValue('collaborator_role'))
            ->set('cool.administrator_role', $form_state->getValue('administrator_role'))
            ->save();

        parent::submitForm($form, $form_state);
  }
}
