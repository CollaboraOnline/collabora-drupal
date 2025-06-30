<?php

declare(strict_types=1);

namespace Drupal\collabora_online\Storage;

/**
 * Storage for WOPI settings files.
 */
interface WopiSettingsStorageInterface {

  /**
   * Lists stored settings files.
   *
   * @param string $prefix
   *   A prefix to filter by.
   *
   * @return array<string, string>
   *   List of files, as stamp by file id.
   */
  public function list(string $prefix): array;

  /**
   * Loads content of a stored settings file.
   *
   * @param string $wopi_file_id
   *   File identifier as "/settings/$type/$category/$name.$extension".
   *
   * @return string|null
   *   File content, or NULL if not found.
   */
  public function read(string $wopi_file_id): ?string;

  /**
   * Writes content of a settings file.
   *
   * @param string $wopi_file_id
   *   File identifier as "/settings/$type/$category/$name.$extension".
   * @param string $content
   *   File content.
   *
   * @return string
   *   New stamp.
   */
  public function write(string $wopi_file_id, string $content): string;

}
