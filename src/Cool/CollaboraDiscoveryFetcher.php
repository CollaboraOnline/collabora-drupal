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

namespace Drupal\collabora_online\Cool;

use Drupal\collabora_online\Exception\CollaboraNotAvailableException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service to load the discovery.xml from the Collabora server.
 */
class CollaboraDiscoveryFetcher {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerChannelInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Gets the contents of discovery.xml from the Collabora server.
   *
   * @param string $server
   *   Url of the Collabora Online server.
   *
   * @return string
   *   The full contents of discovery.xml.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  public function getDiscoveryXml(string $server): string {
    $discovery_url = $server . '/hosting/discovery';

    $cool_settings = $this->configFactory->get('collabora_online.settings')->get('cool');
    $disable_checks = (bool) $cool_settings['disable_cert_check'];

    // Previously, file_get_contents() was used to fetch the discovery xml data.
    // Depending on the environment, it can happen that file_get_contents() will
    // hang at the end of a stream, expecting more data.
    // With curl, this does not happen.
    // @todo Refactor this and use e.g. Guzzle http client.
    $curl = curl_init($discovery_url);
    curl_setopt_array($curl, [
      CURLOPT_RETURNTRANSFER => TRUE,
      // Previously, when this request was done with file_get_contents() and
      // stream_context_create(), the 'verify_peer' and 'verify_peer_name'
      // options were set.
      // @todo Check if an equivalent to 'verify_peer_name' exists for curl.
      CURLOPT_SSL_VERIFYPEER => !$disable_checks,
    ]);
    $xml = curl_exec($curl);

    if ($xml === FALSE) {
      $this->logger->error('Cannot fetch from @url.', ['@url' => $discovery_url]);
      throw new CollaboraNotAvailableException(
        'Not able to retrieve the discovery.xml file from the Collabora Online server.',
        203,
      );
    }
    return $xml;
  }

}
