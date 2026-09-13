<?php

declare(strict_types=1);

namespace Drupal\drupalconf_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles contact form submissions.
 */
final class ContactFormController extends ControllerBase {

  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly FloodInterface $flood,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('flood'),
    );
  }

  /**
   * Handles a contact form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function submit(Request $request): JsonResponse {
    $config = $this->config('drupalconf_api.settings');
    $allowedOrigins = $config->get('allowed_origins') ?? [];
    $origin = $request->headers->get('Origin', '');

    if (!empty($allowedOrigins)) {
      if (!in_array($origin, $allowedOrigins, TRUE)) {
        return new JsonResponse(['error' => 'Forbidden'], 403);
      }
    }
    elseif (Settings::get('environment') === 'production') {
      return new JsonResponse(['error' => 'Forbidden'], 403);
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['error' => 'Invalid JSON'], 400);
    }

    if (!empty($payload['website'])) {
      return new JsonResponse(['ok' => TRUE]);
    }

    $errors = [];
    $subject = isset($payload['subject']) ? trim($payload['subject']) : '';
    $copy = isset($payload['copy']) ? trim($payload['copy']) : '';

    if (mb_strlen($subject) < 1 || mb_strlen($subject) > 255) {
      $errors['subject'] = 'Subject must be between 1 and 255 characters.';
    }
    if (mb_strlen($copy) < 1 || mb_strlen($copy) > 5000) {
      $errors['copy'] = 'Message must be between 1 and 5000 characters.';
    }
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors], 422);
    }

    $clientIp = $request->getClientIp();
    if (!$this->flood->isAllowed('drupalconf_api.contact_form', 5, 3600, $clientIp)) {
      return new JsonResponse(['error' => 'Too many submissions, try again later'], 429);
    }
    $this->flood->register('drupalconf_api.contact_form', 3600, $clientIp);

    $recipient = $config->get('contact_recipient') ?: 'admin@example.com';
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

    $this->mailManager->mail(
      'drupalconf_api',
      'contact_form',
      $recipient,
      $langcode,
      ['subject' => $subject, 'message' => $copy],
    );

    return new JsonResponse(['ok' => TRUE]);
  }

}
