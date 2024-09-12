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
 * @return string|false
 *   The full contents of discovery.xml, or FALSE on failure.
 */
function getDiscovery($server) {
  $discovery_url = $server . '/hosting/discovery';

  $default_config = \Drupal::config('collabora_online.settings');
  $disable_checks = (bool) $default_config->get('cool')['disable_cert_check'];

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
  $res = curl_exec($curl);

  if ($res === FALSE) {
    \Drupal::logger('cool')->error('Cannot fetch from @url.', ['@url' => $discovery_url]);
  }
  return $res;
}

/**
 * Extracts a WOPI url from the parsed discovery.xml.
 *
 * @param \SimpleXMLElement|null|false $discovery_parsed
 *   Parsed contents from discovery.xml from the Collabora server.
 *   Currently, NULL or FALSE are supported too, but lead to NULL return value.
 * @param string $mimetype
 *   MIME type for which to fetch the WOPI url. E.g. 'text/plain'.
 *
 * @return mixed|null
 *   WOPI url as configured for this MIME type in discovery.xml, or NULL if none
 *   was found for the given MIME type.
 */
function getWopiSrcUrl($discovery_parsed, $mimetype) {
  if ($discovery_parsed === NULL || $discovery_parsed == FALSE) {
    return NULL;
  }
  $result = $discovery_parsed->xpath(sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $mimetype));
  if ($result && count($result) > 0) {
    return $result[0]['urlsrc'];
  }
  return NULL;
}

/**
 * Helper class to fetch a WOPI client url.
 */
class CoolRequest {

  /**
   * Gets the URL for the WOPI client.
   *
   * @return string
   *   The WOPI client url.
   *
   * @throws \Drupal\collabora_online\Exception\CollaboraNotAvailableException
   *   The client url cannot be retrieved.
   */
  public function getWopiClientURL() {
    $_HOST_SCHEME = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $default_config = \Drupal::config('collabora_online.settings');
    $wopi_client_server = $default_config->get('cool')['server'];
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

    if (!str_starts_with($wopi_client_server, $_HOST_SCHEME . '://')) {
      throw new CollaboraNotAvailableException(
        'Collabora Online server address scheme does not match the current page url scheme.',
        202,
      );
    }

    $discovery = getDiscovery($wopi_client_server);
    if ($discovery === FALSE) {
      throw new CollaboraNotAvailableException(
        'Not able to retrieve the discovery.xml file from the Collabora Online server.',
        203,
      );
    }

    $discovery_parsed = simplexml_load_string($discovery);
    if (!$discovery_parsed) {
      throw new CollaboraNotAvailableException(
        'The retrieved discovery.xml file is not a valid XML file.',
        102,
      );
    }

    $wopi_src = strval(getWopiSrcUrl($discovery_parsed, 'text/plain')[0]);
    if (!$wopi_src) {
      throw new CollaboraNotAvailableException(
        'The requested mime type is not handled.',
        103,
      );
    }

    return $wopi_src;
  }

}
