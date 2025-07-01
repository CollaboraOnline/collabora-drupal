<?php

declare(strict_types=1);

namespace Drupal\collabora_online\Storage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Storage for WOPI settings files.
 */
class WopiSettingsStorage implements WopiSettingsStorageInterface {

  public const TABLE_NAME = 'collabora_online_settings_files';

  public const URI_PREFIX = 'private://collabora_online';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $connection,
    protected readonly FileSystemInterface $fileSystem,
    #[Autowire(service: 'logger.channel.collabora_online')]
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function list(string $prefix): array {
    $type = match ($prefix) {
      '/settings/userconfig/' => 'userconfig',
      '/settings/systemconfig/' => 'systemconfig',
      default => throw new \InvalidArgumentException("Unexpected prefix '$prefix'."),
    };
    $query = $this->connection->select(self::TABLE_NAME, 'sf');
    $query->condition('type', $type);
    $query->fields('sf', ['fid', 'stamp']);
    $stamps_by_fid = $query->execute()->fetchAllKeyed();
    /** @var array<int, \Drupal\file\FileInterface> $files */
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple(array_keys($stamps_by_fid));
    $missing = array_diff_key($stamps_by_fid, $files);
    if ($missing) {
      $this->logger->warning('Missing files: @missing_files', [
        '@missing_files' => implode(', ', array_keys($missing)),
      ]);
    }
    $result = [];
    foreach ($files as $fid => $file) {
      $uri = $file->getFileUri();
      if (!$uri) {
        // @todo Log this.
        continue;
      }
      $wopi_file_id = $this->getWopiIdFromFileUri($uri);
      if ($wopi_file_id === NULL) {
        // Something messed with the stored files.
        continue;
      }
      $stamp = $stamps_by_fid[$fid];
      $result[$wopi_file_id] = $stamp;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function read(string $wopi_file_id): ?string {
    $uri = $this->getFileUriFromWopiId($wopi_file_id);
    $file = $this->findFileByUri($uri);
    if (!$file) {
      return NULL;
    }
    if (!file_exists($uri) || !is_readable($uri)) {
      $this->logger->warning('File @uri with fid @fid is missing or not readable.', [
        '@fid' => $file->id(),
        '@uri' => var_export($uri, TRUE),
      ]);
      return NULL;
    }
    return file_get_contents($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function write(string $wopi_file_id, string $content, string $stamp): bool {
    if (!preg_match('@^/settings/(userconfig|systemconfig)/\w+/\w+\.\w+@', $wopi_file_id, $matches)) {
      throw new \InvalidArgumentException('Invalid WOPI file id.');
    }
    $type = $matches[1];
    $uri = $this->getFileUriFromWopiId($wopi_file_id);
    $is_new = FALSE;
    if (!file_exists($uri)) {
      $is_new = TRUE;
      $directory = dirname($uri);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    }
    file_put_contents($uri, $content);
    $file = $this->findFileByUri($uri);
    // We assume that $file is NULL exactly when $is_new is TRUE.
    if (!$file) {
      $file = File::create(['uri' => $uri]);
    }
    // Make sure the file 'changed' field is updated.
    $file->save();
    $fid = $file->id();
    $this->connection
      ->upsert(self::TABLE_NAME)
      ->key('fid')
      ->fields(['fid', 'type', 'stamp'])
      ->values([$fid, $type, $stamp])
      ->execute();
    return $is_new;
  }

  /**
   * Finds a file entity with a given uri.
   *
   * @param string $uri
   *   File uri to look for.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if not found.
   */
  protected function findFileByUri(string $uri): ?FileInterface {
    $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri]);
    if (!$files) {
      return NULL;
    }
    return reset($files) ?: NULL;
  }

  /**
   * Builds the file URI for a WOPI file id.
   *
   * @param string $wopi_file_id
   *   Wopi file id like "/settings/$type/$category/$name.$extension".
   *
   * @return string
   *   The Drupal file uri, like "private://...".
   */
  private function getFileUriFromWopiId(string $wopi_file_id): string {
    return self::URI_PREFIX . $wopi_file_id;
  }

  /**
   * Gets the WOPI file id from a Drupal file URI.
   *
   * @param string $uri
   *   The Drupal file URI, like "private://...".
   *
   * @return string|null
   *   The WOPI file ID, or NULL if it does not match.
   */
  private function getWopiIdFromFileUri(string $uri): ?string {
    if (!preg_match('@^' . preg_quote(self::URI_PREFIX, '@') . '(.*)$@', $uri, $matches)) {
      return NULL;
    }
    return $matches[1];
  }

}
