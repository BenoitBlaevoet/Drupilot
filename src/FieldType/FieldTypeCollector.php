<?php

declare(strict_types=1);

namespace Drupal\drupilot\FieldType;

/**
 * Aggregates all tagged field type providers into a single deduplicated list.
 *
 * Injected via `!tagged_iterator drupilot.field_type_provider` so that
 * enabling any drupilot_* sub-module automatically extends the list.
 */
final class FieldTypeCollector {

  /**
   * Constructs the collector.
   *
   * @param iterable<\Drupal\drupilot\FieldType\FieldTypeProviderInterface> $providers
   *   All services tagged with drupilot.field_type_provider.
   */
  public function __construct(
    private readonly iterable $providers,
  ) {}

  /**
   * Returns the merged, deduplicated, sorted list of supported field type IDs.
   *
   * @return string[]
   *   Sorted list of field type machine names.
   */
  public function getSupportedTypes(): array {
    $types = [];
    foreach ($this->providers as $provider) {
      foreach ($provider->getSupportedTypes() as $type) {
        $types[$type] = $type;
      }
    }
    sort($types);
    return $types;
  }

}
