<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\FieldType;

/**
 * Provides field types available in Drupal core without extra modules.
 */
final class CoreFieldTypeProvider implements FieldTypeProviderInterface {

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   The list of supported field type machine names.
   */
  public function getSupportedTypes(): array {
    return [
      'boolean',
      'datetime',
      'decimal',
      'email',
      'entity_reference',
      'file',
      'float',
      'image',
      'integer',
      'list_string',
      'string',
      'text_long',
      'text_with_summary',
    ];
  }

}
