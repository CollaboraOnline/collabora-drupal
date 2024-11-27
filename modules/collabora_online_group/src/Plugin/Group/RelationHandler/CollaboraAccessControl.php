<?php

declare(strict_types=1);

namespace Drupal\collabora_online_group\Plugin\Group\RelationHandler;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlTrait;
use Drupal\group\Plugin\Group\RelationHandlerDefault\AccessControl;

/**
 * Provides access control for collabora group.
 */
class CollaboraAccessControl extends AccessControl {

  use AccessControlTrait;

  /**
   * Constructs a new CollaboraAccessControl.
   *
   * @param \Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface $parent
   *   The default access control.
   */
  public function __construct(AccessControlInterface $parent) {
    $this->parent = $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account, $return_as_object = FALSE): AccessResultInterface|bool {
    // Add support for unpublished operation: preview in collabora.
    $check_published = $operation === 'preview in collabora' && $this->implementsPublishedInterface;

    if ($check_published && !$entity->isPublished()) {
      $operation .= ' unpublished';
    }

    return $this->parent->entityAccess($entity, $operation, $account, $return_as_object);
  }

}
