<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\ValueObject;

/**
 * Public description of a tool — id, metadata, and JSON Schema for inputs.
 */
final readonly class ToolDefinition {

  /**
   * Constructs a tool definition.
   *
   * @param string $id
   *   Tool machine name.
   * @param string $label
   *   Short human label.
   * @param string $description
   *   Sentence-long description.
   * @param string $category
   *   Grouping category.
   * @param array<string, mixed> $inputSchema
   *   JSON Schema (draft-07) describing accepted arguments.
   */
  public function __construct(
    public string $id,
    public string $label,
    public string $description,
    public string $category,
    public array $inputSchema,
  ) {}

}
