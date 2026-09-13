<?php

declare(strict_types=1);

namespace Drupal\drupalconf_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the DrupalConf API settings.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * The settings config name.
   */
  private const SETTINGS = 'drupalconf_api.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupalconf_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);

    $form['contact_recipient'] = [
      '#type' => 'email',
      '#title' => $this->t('Contact form recipient'),
      '#description' => $this->t('Email address that receives contact form submissions.'),
      '#default_value' => $config->get('contact_recipient'),
      '#required' => TRUE,
    ];

    $origins = $config->get('allowed_origins') ?? [];
    $form['allowed_origins'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed origins'),
      '#description' => $this->t('One origin per line (e.g. https://example.com). Contact submissions are only accepted from these origins. Leave empty to allow any origin outside production.'),
      '#default_value' => implode("\n", $origins),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $origins = array_values(array_filter(array_map(
      trim(...),
      explode("\n", (string) $form_state->getValue('allowed_origins')),
    )));

    $this->config(self::SETTINGS)
      ->set('contact_recipient', $form_state->getValue('contact_recipient'))
      ->set('allowed_origins', $origins)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
