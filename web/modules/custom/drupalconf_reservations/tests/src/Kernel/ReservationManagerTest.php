<?php

declare(strict_types=1);

namespace Drupal\Tests\drupalconf_reservations\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\drupalconf_reservations\Entity\Reservation;
use Drupal\drupalconf_reservations\ReservationManager;
use Drupal\drupalconf_reservations\ReservationStatus;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests reservation capacity, lookup, and access logic.
 */
#[Group('drupalconf_reservations')]
final class ReservationManagerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'node',
    'drupalconf_reservations',
  ];

  /**
   * The reservation manager under test.
   */
  private ReservationManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('reservation');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['user']);
    // Take uid 1 so test users are not superusers.
    $this->setUpCurrentUser();

    NodeType::create(['type' => 'session', 'name' => 'Session'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_max_seats',
      'entity_type' => 'node',
      'type' => 'integer',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_max_seats',
      'entity_type' => 'node',
      'bundle' => 'session',
      'label' => 'Max seats',
    ])->save();

    $this->manager = $this->container->get('drupalconf_reservations.manager');
  }

  /**
   * Capacity is enforced and seats_remaining tracks reservations.
   */
  public function testCapacity(): void {
    $session = $this->createSession(2);

    $this->assertSame(2, $this->manager->seatsRemaining($session));
    $this->assertTrue($this->manager->hasCapacity($session));

    $this->reserve($session, 'a@example.com');
    $this->assertSame(1, $this->manager->seatsRemaining($session));
    $this->assertTrue($this->manager->hasCapacity($session));

    $this->reserve($session, 'b@example.com');
    $this->assertSame(0, $this->manager->seatsRemaining($session));
    $this->assertFalse($this->manager->hasCapacity($session));
  }

  /**
   * A session without a seat limit is unlimited.
   */
  public function testUnlimitedSession(): void {
    $session = $this->createSession(NULL);

    $this->assertNull($this->manager->seatsRemaining($session));
    $this->assertTrue($this->manager->hasCapacity($session));

    $this->reserve($session, 'a@example.com');
    $this->assertNull($this->manager->seatsRemaining($session));
    $this->assertTrue($this->manager->hasCapacity($session));
  }

  /**
   * Cancelled reservations do not consume capacity.
   */
  public function testCancelledDoesNotConsumeCapacity(): void {
    $session = $this->createSession(1);
    $this->reserve($session, 'a@example.com', ReservationStatus::Cancelled);

    $this->assertSame(1, $this->manager->seatsRemaining($session));
    $this->assertTrue($this->manager->hasCapacity($session));
  }

  /**
   * Duplicate active reservations are detected per email + session.
   */
  public function testAlreadyReserved(): void {
    $session = $this->createSession(10);
    $this->reserve($session, 'a@example.com');

    $this->assertTrue($this->manager->alreadyReserved($session, 'a@example.com'));
    $this->assertFalse($this->manager->alreadyReserved($session, 'b@example.com'));
  }

  /**
   * Lookup returns only the active reservations for the given email.
   */
  public function testFindByEmail(): void {
    $session1 = $this->createSession(10);
    $session2 = $this->createSession(10);
    $this->reserve($session1, 'a@example.com');
    $this->reserve($session2, 'a@example.com');
    $this->reserve($session1, 'b@example.com');
    $this->reserve($session2, 'a@example.com', ReservationStatus::Cancelled);

    $found = $this->manager->findByEmail('a@example.com');
    $this->assertCount(2, $found);
    $this->assertCount(0, $this->manager->findByEmail('nobody@example.com'));
  }

  /**
   * Reservations are not viewable without the admin permission.
   */
  public function testAccessControl(): void {
    $session = $this->createSession(10);
    $reservation = $this->reserve($session, 'a@example.com');

    $anonymous = User::getAnonymousUser();
    $this->assertFalse($reservation->access('view', $anonymous));

    $authenticated = $this->createUser([]);
    $this->assertFalse($reservation->access('view', $authenticated));

    $admin = $this->createUser(['administer reservations']);
    $this->assertTrue($reservation->access('view', $admin));
  }

  /**
   * Creates a published Session node with an optional seat limit.
   */
  private function createSession(?int $maxSeats): NodeInterface {
    $values = ['type' => 'session', 'title' => 'Test session', 'status' => 1];
    if ($maxSeats !== NULL) {
      $values['field_max_seats'] = $maxSeats;
    }
    $node = Node::create($values);
    $node->save();
    return $node;
  }

  /**
   * Creates a reservation without sending email.
   */
  private function reserve(NodeInterface $session, string $email, ReservationStatus $status = ReservationStatus::Reserved): Reservation {
    /** @var \Drupal\drupalconf_reservations\Entity\Reservation $reservation */
    $reservation = $this->container->get('entity_type.manager')
      ->getStorage('reservation')
      ->create([
        'session' => $session->id(),
        'email' => $email,
        'name' => 'Test Attendee',
        'status' => $status->value,
      ]);
    $reservation->save();
    return $reservation;
  }

}
