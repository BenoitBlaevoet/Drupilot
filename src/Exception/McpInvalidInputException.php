<?php

declare(strict_types=1);

namespace Drupal\drupilot\Exception;

/**
 * Thrown when a tool's input fails schema or business validation.
 */
final class McpInvalidInputException extends McpException {

  /**
   * Constructs the exception.
   *
   * @param string $message
   *   Human-readable summary.
   * @param array<string, string> $violations
   *   Map of input path => violation message.
   */
  public function __construct(
    string $message,
    private readonly array $violations = [],
  ) {
    parent::__construct($message);
  }

  /**
   * Returns the structured violations for clients to consume.
   *
   * @return array<string, string>
   *   Map of input path => violation message.
   */
  public function getViolations(): array {
    return $this->violations;
  }

}
