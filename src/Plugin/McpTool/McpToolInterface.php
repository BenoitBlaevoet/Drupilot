<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\drupilot\ValueObject\McpResponse;

/**
 * Contract every MCP tool plugin must implement.
 */
interface McpToolInterface extends ContainerFactoryPluginInterface {

  /**
   * Returns a JSON Schema (draft-07) describing the accepted arguments.
   *
   * @return array<string, mixed>
   *   The JSON Schema object.
   */
  public function getInputSchema(): array;

  /**
   * Executes the tool.
   *
   * Implementations MUST catch internal exceptions and convert them to error
   * responses. This method must never throw toward the caller.
   *
   * @param array<string, mixed> $input
   *   Arguments forwarded from the MCP request.
   *
   * @return \Drupal\drupilot\ValueObject\McpResponse
   *   The response value object.
   */
  public function execute(array $input): McpResponse;

}
