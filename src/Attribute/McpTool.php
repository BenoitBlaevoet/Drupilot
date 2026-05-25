<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Declares a class as an MCP tool plugin.
 *
 * Pure metadata: carries the tool identifier, human label, description, and
 * category used to group tools in the administration UI.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class McpTool extends Plugin {

  /**
   * Constructs the attribute.
   *
   * @param string $id
   *   Machine-name identifier of the tool (e.g. "content_type_create").
   * @param string $label
   *   Short human label.
   * @param string $description
   *   Sentence-long description of the tool's behaviour.
   * @param string $category
   *   Grouping category (e.g. "content_type", "field", "node").
   */
  public function __construct(
    public readonly string $id,
    public readonly string $label,
    public readonly string $description,
    public readonly string $category,
  ) {}

}
