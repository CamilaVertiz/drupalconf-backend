<?php

declare(strict_types=1);

namespace Drupal\drupalconf_reservations;

/**
 * The lifecycle status of a reservation.
 */
enum ReservationStatus: string {

  case Reserved = 'reserved';
  case Cancelled = 'cancelled';

  /**
   * Returns the allowed values map for a list_string field.
   *
   * @return array<string, string>
   *   Status value => human-readable label.
   */
  public static function allowedValues(): array {
    return [
      self::Reserved->value => 'Reserved',
      self::Cancelled->value => 'Cancelled',
    ];
  }

}
