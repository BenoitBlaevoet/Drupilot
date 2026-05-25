<?php

declare(strict_types=1);

namespace Drupal\drupilot\Exception;

/**
 * Thrown when a tool referenced by id does not exist in the plugin registry.
 */
final class McpToolNotFoundException extends McpException {

  /**
   * Constructs the exception with a human-friendly message.
   */
  public function __construct(string $toolId) {
    parent::__construct(sprintf("MCP tool '%s' does not exist.", $toolId));
  }

}
