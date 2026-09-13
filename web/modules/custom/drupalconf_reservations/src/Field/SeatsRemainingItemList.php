<?php

declare(strict_types=1);

namespace Drupal\drupalconf_reservations\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\node\NodeInterface;

/**
 * Computes the remaining seats for a session node.
 *
 * @extends \Drupal\Core\Field\FieldItemList<\Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem>
 */
final class SeatsRemainingItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $session = $this->getEntity();
    if (!$session instanceof NodeInterface) {
      return;
    }

    /** @var \Drupal\drupalconf_reservations\ReservationManager $manager */
    $manager = \Drupal::service('drupalconf_reservations.manager');
    $remaining = $manager->seatsRemaining($session);
    if ($remaining !== NULL) {
      $this->list[0] = $this->createItem(0, $remaining);
    }
  }

}
