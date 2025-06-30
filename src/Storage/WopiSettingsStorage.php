<?php

declare(strict_types=1);

namespace Drupal\collabora_online\Storage;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Storage for WOPI settings files.
 */
class WopiSettingsStorage implements WopiSettingsStorageInterface {

  public function __construct(
    #[Autowire(service: 'keyvalue')]
    protected readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function list(string $prefix): array {
    $all = $this->getStampsKeyValueStore()->getAll();
    return array_filter(
      $all,
      fn (string $wopi_file_id) => str_starts_with($wopi_file_id, $prefix),
      ARRAY_FILTER_USE_KEY,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function read(string $wopi_file_id): ?string {
    return $this->getStorageKeyValueStore()->get($wopi_file_id);
  }

  /**
   * {@inheritdoc}
   */
  public function write(string $wopi_file_id, string $content): string {
    $stamp = uniqid();
    $this->getStorageKeyValueStore()->set($wopi_file_id, $content);
    $this->getStampsKeyValueStore()->set($wopi_file_id, $stamp);
    return $stamp;
  }

  /**
   * Gets a key-value store to store stamps.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   Key-value store.
   */
  protected function getStampsKeyValueStore(): KeyValueStoreInterface {
    return $this->keyValueFactory->get('collabora_online.settings.meta');
  }

  /**
   * Gets a key-value store to store settings file contents.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   Key-value store.
   */
  protected function getStorageKeyValueStore(): KeyValueStoreInterface {
    return $this->keyValueFactory->get('collabora_online.settings.data');
  }

}
