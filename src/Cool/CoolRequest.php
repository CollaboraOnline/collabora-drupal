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

namespace Drupal\collabora_online\Cool;

use Drupal\collabora_online\Exception\CollaboraNotAvailableException;

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
function getDiscoveryXml(string $server): string {
  $discovery_url = $server . '/hosting/discovery';

  $cool_settings = \Drupal::config('collabora_online.settings')->get('cool');
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
    \Drupal::logger('cool')->error('Cannot fetch from @url.', ['@url' => $discovery_url]);
    throw new CollaboraNotAvailableException(
      'Not able to retrieve the discovery.xml file from the Collabora Online server.',
      203,
    );
  }
  return $xml;
}

/**
 * Helper class to fetch a WOPI client url.
 */
class CoolRequest {

  /**
   * Gets the URL for the WOPI client.
   *
   * @param string $mimetype
   *   Mime type for which to get the WOPI client url.
   *   This refers to config entries in the discovery.xml file.
   *
   * @return string
   *   The WOPI client url.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  public function getWopiClientURL(string $mimetype = 'text/plain'): string {
    $cool_settings = \Drupal::config('collabora_online.settings')->get('cool');
    $wopi_client_server = $cool_settings['server'];
    if (!$wopi_client_server) {
      throw new CollaboraNotAvailableException(
        'Collabora Online server address is not valid.',
        201,
      );
    }
    $wopi_client_server = trim($wopi_client_server);

    if (!str_starts_with($wopi_client_server, 'http')) {
      throw new CollaboraNotAvailableException(
        'Warning! You have to specify the scheme protocol too (http|https) for the server address.',
        204,
      );
    }

    $host_scheme = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    if (!str_starts_with($wopi_client_server, $host_scheme . '://')) {
      throw new CollaboraNotAvailableException(
        'Collabora Online server address scheme does not match the current page url scheme.',
        202,
      );
    }

    $xml = getDiscoveryXml($wopi_client_server);

    $discovery_parsed = simplexml_load_string($xml);
    if (!$discovery_parsed) {
      throw new CollaboraNotAvailableException(
        'The retrieved discovery.xml file is not a valid XML file.',
        102,
      );
    }

    $result = $discovery_parsed->xpath(sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $mimetype));
    if (empty($result[0]['urlsrc'][0])) {
      throw new CollaboraNotAvailableException(
        'The requested mime type is not handled.',
        103,
      );
    }

    return (string) $result[0]['urlsrc'][0];
  }

}
