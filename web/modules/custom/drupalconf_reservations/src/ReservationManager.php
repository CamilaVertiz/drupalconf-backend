<?php

declare(strict_types=1);

namespace Drupal\drupalconf_reservations;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\drupalconf_reservations\Entity\Reservation;
use Drupal\node\NodeInterface;

/**
 * Manages session reservations.
 */
final class ReservationManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Loads a published Session node by UUID.
   */
  public function loadSession(string $uuid): ?NodeInterface {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'uuid' => $uuid,
      'type' => 'session',
    ]);
    $session = $nodes ? reset($nodes) : NULL;
    return ($session instanceof NodeInterface && $session->isPublished()) ? $session : NULL;
  }

  /**
   * Counts the active reservations for a session.
   */
  public function reservedCount(NodeInterface $session): int {
    $count = $this->entityTypeManager->getStorage('reservation')->getQuery()
      ->accessCheck(FALSE)
      ->condition('session', $session->id())
      ->condition('status', ReservationStatus::Reserved->value)
      ->count()
      ->execute();
    return (int) $count;
  }

  /**
   * Returns the number of seats left, or NULL when the session is unlimited.
   */
  public function seatsRemaining(NodeInterface $session): ?int {
    if (!$session->hasField('field_max_seats') || $session->get('field_max_seats')->isEmpty()) {
      return NULL;
    }
    $max = (int) $session->get('field_max_seats')->value;
    return max(0, $max - $this->reservedCount($session));
  }

  /**
   * Checks whether the session has seats available.
   */
  public function hasCapacity(NodeInterface $session): bool {
    $remaining = $this->seatsRemaining($session);
    return $remaining === NULL || $remaining > 0;
  }

  /**
   * Checks whether the email already has a reservation for the session.
   */
  public function alreadyReserved(NodeInterface $session, string $email): bool {
    $ids = $this->entityTypeManager->getStorage('reservation')->getQuery()
      ->accessCheck(FALSE)
      ->condition('session', $session->id())
      ->condition('email', $email)
      ->condition('status', ReservationStatus::Reserved->value)
      ->range(0, 1)
      ->execute();
    return !empty($ids);
  }

  /**
   * Creates a reservation and sends a confirmation email.
   */
  public function create(NodeInterface $session, string $email, string $name, ?string $notes = NULL): Reservation {
    $storage = $this->entityTypeManager->getStorage('reservation');
    /** @var \Drupal\drupalconf_reservations\Entity\Reservation $reservation */
    $reservation = $storage->create([
      'session' => $session->id(),
      'email' => $email,
      'name' => $name,
      'notes' => $notes,
      'status' => ReservationStatus::Reserved->value,
    ]);
    $reservation->save();

    $this->mail('reservation_confirmation', $email, [
      'name' => $name,
      'session_title' => (string) $session->label(),
    ]);

    return $reservation;
  }

  /**
   * Loads the active reservations held by an email address.
   *
   * @return array<int, \Drupal\drupalconf_reservations\Entity\Reservation>
   *   The matching reservations.
   */
  public function findByEmail(string $email): array {
    $storage = $this->entityTypeManager->getStorage('reservation');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('email', $email)
      ->condition('status', ReservationStatus::Reserved->value)
      ->execute();
    /** @var array<int, \Drupal\drupalconf_reservations\Entity\Reservation> $reservations */
    $reservations = $storage->loadMultiple($ids);
    return array_values($reservations);
  }

  /**
   * Emails a list of reservations to an address.
   *
   * @param string $email
   *   The recipient email address.
   * @param array<int, \Drupal\drupalconf_reservations\Entity\Reservation> $reservations
   *   The reservations.
   */
  public function emailReservationDetails(string $email, array $reservations): void {
    $lines = [];
    foreach ($reservations as $reservation) {
      $session = $reservation->get('session')->entity;
      $lines[] = $session instanceof NodeInterface ? (string) $session->label() : 'A session';
    }
    $this->mail('reservation_lookup', $email, ['sessions' => $lines]);
  }

  /**
   * Sends an email.
   *
   * @param string $key
   *   The hook_mail() message key.
   * @param string $to
   *   The recipient email address.
   * @param array<string, mixed> $params
   *   Parameters passed to hook_mail().
   */
  private function mail(string $key, string $to, array $params): void {
    $langcode = $this->languageManager->getDefaultLanguage()->getId();
    $this->mailManager->mail('drupalconf_reservations', $key, $to, $langcode, $params);
  }

}
