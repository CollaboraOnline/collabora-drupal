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

namespace Drupal\collabora_online;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides permissions for Collabora per media type.
 *
 * @see \Drupal\media\MediaPermissions
 */
class CollaboraMediaPermissions implements ContainerInjectionInterface {

  use AutowireTrait;
  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns an array of media type permissions.
   *
   * @return array
   *   The media type permissions.
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function mediaTypePermissions(): array {
    // Generate media permissions for all media types.
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    return $this->generatePermissions($media_types, [$this, 'buildPermissions']);
  }

  /**
   * Returns a list of permissions for a given media type.
   *
   * @param \Drupal\media\MediaTypeInterface $type
   *   The media type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(MediaTypeInterface $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "preview $type_id in collabora" => [
        'title' => $this->t('%type_name: Preview published media file in Collabora', $type_params),
      ],
      "preview own unpublished $type_id in collabora" => [
        'title' => $this->t('%type_name: Preview own unpublished media file in Collabora', $type_params),
      ],
      "edit own $type_id in collabora" => [
        'title' => $this->t('%type_name: Edit own media file in Collabora', $type_params),
      ],
      "edit any $type_id in collabora" => [
        'title' => $this->t('%type_name: Edit any media file in Collabora', $type_params),
      ],
    ];
  }

}
