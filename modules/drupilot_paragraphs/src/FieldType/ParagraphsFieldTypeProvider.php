<?php

declare(strict_types=1);

namespace Drupal\drupilot_paragraphs\FieldType;

use Drupal\drupilot\FieldType\FieldTypeProviderInterface;

/**
 * Provides the 'entity_reference_revisions' field type (requires paragraphs).
 */
final class ParagraphsFieldTypeProvider implements FieldTypeProviderInterface {

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   The list of supported field type machine names.
   */
  public function getSupportedTypes(): array {
    return ['entity_reference_revisions'];
  }

}
