<?php

declare(strict_types=1);

namespace Drupal\drupilot_telephone\FieldType;

use Drupal\drupilot\FieldType\FieldTypeProviderInterface;

/**
 * Provides the 'telephone' field type (requires drupal:telephone).
 */
final class TelephoneFieldTypeProvider implements FieldTypeProviderInterface {

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   The list of supported field type machine names.
   */
  public function getSupportedTypes(): array {
    return ['telephone'];
  }

}
