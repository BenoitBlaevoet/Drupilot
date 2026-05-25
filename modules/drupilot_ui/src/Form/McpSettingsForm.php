<?php

declare(strict_types=1);

namespace Drupal\drupilot_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages the bearer token used to authenticate MCP requests.
 */
final class McpSettingsForm extends ConfigFormBase {

  private const string CONFIG_NAME = 'drupilot.settings';
  private const int MIN_TOKEN_LENGTH = 32;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupilot_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, string>
   *   The list of editable configuration names.
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The augmented form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);
    $current = $config->get('bearer_token');
    $hasToken = is_string($current) && $current !== '';

    $form['bearer_token'] = [
      '#type' => 'password',
      '#title' => $this->t('Bearer token'),
      '#description' => $this->t(
        'Minimum @len characters. Leave empty to keep the current token. Status: @status.',
        [
          '@len' => self::MIN_TOKEN_LENGTH,
          '@status' => $hasToken ? $this->t('configured') : $this->t('not set'),
        ],
      ),
      '#size' => 64,
      '#maxlength' => 255,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @phpstan-ignore-next-line parameterByRef.type
    parent::validateForm($form, $form_state);

    $value = $form_state->getValue('bearer_token');
    if (!is_string($value) || $value === '') {
      return;
    }

    if (strlen($value) < self::MIN_TOKEN_LENGTH) {
      $form_state->setErrorByName(
        'bearer_token',
        $this->t('The bearer token must be at least @len characters.', ['@len' => self::MIN_TOKEN_LENGTH]),
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $value = $form_state->getValue('bearer_token');
    if (is_string($value) && $value !== '') {
      $this->config(self::CONFIG_NAME)
        ->set('bearer_token', $value)
        ->save();
    }

    // @phpstan-ignore-next-line parameterByRef.type
    parent::submitForm($form, $form_state);
  }

}
