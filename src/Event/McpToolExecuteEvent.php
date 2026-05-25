<?php

declare(strict_types=1);

namespace Drupal\drupilot\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched just before a tool's execute() method is invoked.
 *
 * Subscribers may inspect or mutate the arguments passed to the tool.
 */
final class McpToolExecuteEvent extends Event {

  public const string EVENT_NAME = 'drupilot.tool_execute';

  /**
   * Constructs the event.
   *
   * @param string $toolId
   *   Id of the tool about to be executed.
   * @param array<string, mixed> $arguments
   *   Arguments to be forwarded to the tool.
   */
  public function __construct(
    private readonly string $toolId,
    private array $arguments,
  ) {}

  /**
   * Returns the tool id.
   */
  public function getToolId(): string {
    return $this->toolId;
  }

  /**
   * Returns the current arguments.
   *
   * @return array<string, mixed>
   *   The argument map.
   */
  public function getArguments(): array {
    return $this->arguments;
  }

  /**
   * Replaces the arguments.
   *
   * @param array<string, mixed> $arguments
   *   The new argument map.
   */
  public function setArguments(array $arguments): void {
    $this->arguments = $arguments;
  }

}
