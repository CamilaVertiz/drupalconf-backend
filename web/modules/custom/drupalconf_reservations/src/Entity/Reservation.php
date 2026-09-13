<?php

declare(strict_types=1);

namespace Drupal\drupalconf_reservations\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drupalconf_reservations\ReservationAccessControlHandler;
use Drupal\drupalconf_reservations\ReservationStatus;

/**
 * Defines the Reservation entity.
 */
#[ContentEntityType(
  id: 'reservation',
  label: new TranslatableMarkup('Reservation'),
  label_collection: new TranslatableMarkup('Reservations'),
  base_table: 'reservation',
  admin_permission: 'administer reservations',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'email',
  ],
  handlers: [
    'access' => ReservationAccessControlHandler::class,
  ],
)]
final class Reservation extends ContentEntityBase {

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string, \Drupal\Core\Field\BaseFieldDefinition>
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['session'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Session'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', ['target_bundles' => ['session' => 'session']])
      ->setRequired(TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Attendee email'))
      ->setRequired(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Attendee name'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Notes'));

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setSetting('allowed_values', ReservationStatus::allowedValues())
      ->setDefaultValue(ReservationStatus::Reserved->value)
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);
    $this->invalidateSessionCache();
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<int, \Drupal\drupalconf_reservations\Entity\Reservation> $entities
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);
    foreach ($entities as $entity) {
      $entity->invalidateSessionCache();
    }
  }

  /**
   * Invalidates the referenced session's cache tags.
   */
  protected function invalidateSessionCache(): void {
    $session = $this->get('session')->entity;
    if ($session instanceof EntityInterface) {
      Cache::invalidateTags($session->getCacheTagsToInvalidate());
    }
  }

}
