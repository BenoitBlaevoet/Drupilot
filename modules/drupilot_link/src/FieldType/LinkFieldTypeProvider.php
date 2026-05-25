<?php

declare(strict_types=1);

namespace Drupal\drupilot_link\FieldType;

use Drupal\drupilot\FieldType\FieldTypeProviderInterface;

/**
 * Provides the 'link' field type (requires drupal:link).
 */
final class LinkFieldTypeProvider implements FieldTypeProviderInterface {

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   The list of supported field type machine names.
   */
  public function getSupportedTypes(): array {
    return ['link'];
  }

}
