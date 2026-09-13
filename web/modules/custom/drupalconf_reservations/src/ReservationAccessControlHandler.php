<?php

declare(strict_types=1);

namespace Drupal\drupalconf_reservations;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access handler for reservations.
 */
final class ReservationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer reservations');
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $context
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer reservations');
  }

}
