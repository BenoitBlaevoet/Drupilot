<?php

declare(strict_types=1);

namespace Drupal\drupilot_daterange\FieldType;

use Drupal\drupilot\FieldType\FieldTypeProviderInterface;

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
