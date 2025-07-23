<?php

declare(strict_types=1);

namespace Drupal\collabora_online\Storage;

/**
 * Storage for WOPI settings files.
 */
interface WopiSettingsStorageInterface {

  /**
   * Determines whether this storage is available.
   *
   * @return bool
   *   TRUE if available, FALSE if not.
   */
  public function isAvailable(): bool;

  /**
   * Lists stored settings files.
   *
   * @param 'userconfig'|'systemconfig' $type
   *   The type to filter by.
   *   This is the second fragment in a WOPI file ID.
   *
   * @return array<string, string>
   *   List of files, as stamp by file id.
   */
  public function list(string $type): array;

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
   * @param string $stamp
   *   A hash or random string to distinguish from older versions.
   *
   * @return bool
   *   TRUE if newly created, FALSE if updated.
   */
  public function write(string $wopi_file_id, string $content, string $stamp): bool;

  /**
   * Deletes a stored settings file.
   *
   * Currently this is only used in tests.
   *
   * @return bool
   *   TRUE if the file was deleted successfully, FALSE if it did not exist.
   */
  public function delete(string $wopi_file_id): bool;

}
