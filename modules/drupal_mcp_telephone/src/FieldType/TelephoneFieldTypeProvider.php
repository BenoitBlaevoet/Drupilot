<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp_telephone\FieldType;

use Drupal\drupal_mcp\FieldType\FieldTypeProviderInterface;

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
