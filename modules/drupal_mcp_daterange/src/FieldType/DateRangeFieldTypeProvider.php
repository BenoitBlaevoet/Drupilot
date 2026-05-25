<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp_daterange\FieldType;

use Drupal\drupal_mcp\FieldType\FieldTypeProviderInterface;

/**
 * Provides the 'daterange' field type (requires drupal:datetime_range).
 */
final class DateRangeFieldTypeProvider implements FieldTypeProviderInterface {

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   The list of supported field type machine names.
   */
  public function getSupportedTypes(): array {
    return ['daterange'];
  }

}
