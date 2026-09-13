<?php

declare(strict_types=1);

namespace Drupal\drupalconf_reservations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\drupalconf_reservations\ReservationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public reservation endpoints: create a reservation and look one up by email.
 */
final class ReservationController extends ControllerBase {

  public function __construct(
    private readonly ReservationManager $manager,
    private readonly FloodInterface $flood,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('drupalconf_reservations.manager'),
      $container->get('flood'),
    );
  }

  /**
   * Creates a reservation for a session.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function createReservation(Request $request): JsonResponse {
    $payload = $this->decode($request);
    if ($payload === NULL) {
      return new JsonResponse(['error' => 'Invalid JSON'], 400);
    }

    // Honeypot.
    if (!empty($payload['website'])) {
      return new JsonResponse(['ok' => TRUE]);
    }

    $clientIp = $request->getClientIp();
    if (!$this->flood->isAllowed('drupalconf_reservations.create', 20, 3600, $clientIp)) {
      return new JsonResponse(['error' => 'Too many reservations, try again later'], 429);
    }

    $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
    $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
    $sessionUuid = isset($payload['session']) ? trim((string) $payload['session']) : '';
    $notes = isset($payload['notes']) ? trim((string) $payload['notes']) : NULL;

    $errors = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'A valid email address is required.';
    }
    if ($name === '' || mb_strlen($name) > 255) {
      $errors['name'] = 'Name must be between 1 and 255 characters.';
    }
    if ($sessionUuid === '') {
      $errors['session'] = 'A session is required.';
    }
    if ($errors) {
      return new JsonResponse(['errors' => $errors], 422);
    }

    $session = $this->manager->loadSession($sessionUuid);
    if (!$session) {
      return new JsonResponse(['error' => 'Session not found'], 404);
    }

    if ($this->manager->alreadyReserved($session, $email)) {
      return new JsonResponse(['error' => 'You already have a reservation for this session'], 409);
    }
    if (!$this->manager->hasCapacity($session)) {
      return new JsonResponse(['error' => 'This session is full'], 409);
    }

    $this->flood->register('drupalconf_reservations.create', 3600, $clientIp);
    $this->manager->create($session, $email, $name, $notes !== '' ? $notes : NULL);

    return new JsonResponse(['ok' => TRUE], 201);
  }

  /**
   * Looks up reservations by email and emails the details to that address.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function lookup(Request $request): JsonResponse {
    $payload = $this->decode($request);
    if ($payload === NULL) {
      return new JsonResponse(['error' => 'Invalid JSON'], 400);
    }

    if (!empty($payload['website'])) {
      return new JsonResponse(['found' => FALSE]);
    }

    $clientIp = $request->getClientIp();
    if (!$this->flood->isAllowed('drupalconf_reservations.lookup', 10, 3600, $clientIp)) {
      return new JsonResponse(['error' => 'Too many lookups, try again later'], 429);
    }
    $this->flood->register('drupalconf_reservations.lookup', 3600, $clientIp);

    $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return new JsonResponse(['errors' => ['email' => 'A valid email address is required.']], 422);
    }

    $reservations = $this->manager->findByEmail($email);
    if (!$reservations) {
      return new JsonResponse(['found' => FALSE]);
    }

    $this->manager->emailReservationDetails($email, $reservations);
    return new JsonResponse([
      'found' => TRUE,
      'message' => 'We have emailed your reservation details to that address.',
    ]);
  }

  /**
   * Decodes the JSON request body.
   *
   * @return array<string, mixed>|null
   *   The decoded payload, or NULL if invalid.
   */
  private function decode(Request $request): ?array {
    $payload = json_decode($request->getContent(), TRUE);
    return is_array($payload) ? $payload : NULL;
  }

}
