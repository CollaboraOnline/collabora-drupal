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
  if ($default_config === NULL) {
    return FALSE;
  }
  $disable_checks = (bool) $default_config->get('cool')['disable_cert_check'];

  $stream_context = stream_context_create([
    'ssl' => [
      'verify_peer'       => !$disable_checks,
      'verify_peer_name'  => !$disable_checks,
    ],
  ]);
  $res = file_get_contents($discovery_url, FALSE, $stream_context);
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
 * Checks if a string starts with another string.
 *
 * @param string $s
 *   Haystack.
 * @param string $ss
 *   Needle.
 *
 * @return bool
 *   TRUE if $ss is a prefix of $s.
 *
 * @see str_starts_with()
 */
function strStartsWith($s, $ss) {
  $res = strrpos($s, $ss);
  return !is_bool($res) && $res == 0;
}

/**
 * Helper class to fetch a WOPI client url.
 */
class CoolRequest {

  /**
   * Error code from last attempt to fetch the client WOPI url.
   *
   * @var int
   */
  private $error_code;

  const ERROR_MSG = [
    0 => 'Success',
    101 => 'GET Request not found.',
    201 => 'Collabora Online server address is not valid.',
    202 => 'Collabora Online server address scheme does not match the current page url scheme.',
    203 => 'Not able to retrieve the discovery.xml file from the Collabora Online server.',
    102 => 'The retrieved discovery.xml file is not a valid XML file.',
    103 => 'The requested mime type is not handled.',
    204 => 'Warning! You have to specify the scheme protocol too (http|https) for the server address.',
  ];

  /**
   * The WOPI url that was last fetched, or '' as initial value.
   *
   * @var int
   */
  private $wopi_src;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->error_code = 0;
    $this->wopi_src = '';
  }

  /**
   * Gets an error string from the last attempt to fetch the WOPI url.
   *
   * @return string
   *   Error string containing int error code and a message.
   */
  public function errorString() {
    return $this->error_code . ': ' . static::ERROR_MSG[$this->error_code];
  }

  /**
   * Gets the URL for the WOPI client.
   *
   * @return string|null
   *   The WOPI client url, or NULL on failure.
   */
  public function getWopiClientURL() {
    $_HOST_SCHEME = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $default_config = \Drupal::config('collabora_online.settings');
    $wopi_client_server = $default_config->get('cool')['server'];
    if (!$wopi_client_server) {
      $this->error_code = 201;
      return NULL;
    }
    $wopi_client_server = trim($wopi_client_server);

    if (!strStartsWith($wopi_client_server, 'http')) {
      $this->error_code = 204;
      return NULL;
    }

    if (!strStartsWith($wopi_client_server, $_HOST_SCHEME . '://')) {
      $this->error_code = 202;
      return NULL;
    }

    $discovery = getDiscovery($wopi_client_server);
    if ($discovery === FALSE) {
      $this->error_code = 203;
      return NULL;
    }

    $discovery_parsed = simplexml_load_string($discovery);
    if (!$discovery_parsed) {
      $this->error_code = 102;
      return NULL;
    }

    $this->wopi_src = strval(getWopiSrcUrl($discovery_parsed, 'text/plain')[0]);
    if (!$this->wopi_src) {
      $this->error_code = 103;
      return NULL;
    }

    return $this->wopi_src;
  }

}
