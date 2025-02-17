<?php

/*
 * This code is mostly copied from the 'openeuropa/oe_showcase' package, which
 * is authored by the OpenEuropa team, and published under the EUPL-1.2 license.
 *
 * It is re-licensed under the MPL license, as permitted in the compatibilty
 * clause of the EUPL.
 *
 * SPDX-License-Identifier: MPL-2.0
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Traits;

/**
 * Used for backing up configuration in ExistingSite tests.
 */
trait ConfigurationBackupTrait {

  /**
   * Simple config objects.
   *
   * @var array
   */
  protected array $backupSimpleConfig = [];

  /**
   * Config entities.
   *
   * @var array
   */
  protected array $backupConfigEntities = [];

  /**
   * Backs up a simple configuration object.
   *
   * @param string $name
   *   The configuration name.
   */
  protected function backupSimpleConfig(string $name): void {
    $config = \Drupal::configFactory()->get($name);
    $this->backupSimpleConfig[$name] = $config->getRawData();
  }

  /**
   * Backs up a config entity object.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $id
   *   The configuration ID.
   */
  protected function backupConfigEntity(string $entity_type, string $id): void {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($id);
    $this->backupConfigEntities[$entity_type][$id] = $entity->toArray();
  }

  /**
   * Restores backed-up configuration.
   *
   * @after
   */
  public function restoreConfiguration(): void {
    foreach ($this->backupSimpleConfig as $name => $values) {
      \Drupal::configFactory()->getEditable($name)->setData($values)->save();
    }

    foreach ($this->backupConfigEntities as $entity_type => $ids) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      foreach ($ids as $id => $values) {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
        $entity = $storage->load($id);
        $storage->updateFromStorageRecord($entity, $values);

        $entity->save();
      }
    }
  }

}
