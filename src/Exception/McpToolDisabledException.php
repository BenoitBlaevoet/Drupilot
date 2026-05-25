<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Exception;

/**
 * Thrown when a tool is invoked while disabled in configuration.
 */
final class McpToolDisabledException extends McpException {

  /**
   * Constructs the exception with a human-friendly message.
   */
  public function __construct(string $toolId) {
    parent::__construct(sprintf("MCP tool '%s' is disabled.", $toolId));
  }

}
