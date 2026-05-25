<?php

declare(strict_types=1);

namespace Drupal\drupilot\Event;

use Drupal\drupilot\ValueObject\McpRequest;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before an MCP request is handled by the server service.
 *
 * Subscribers may inspect or replace the request value object.
 */
final class McpServerRequestEvent extends Event {

  public const string EVENT_NAME = 'drupilot.server_request';

  /**
   * Constructs the event.
   */
  public function __construct(
    private McpRequest $request,
  ) {}

  /**
   * Returns the request value object.
   */
  public function getRequest(): McpRequest {
    return $this->request;
  }

  /**
   * Replaces the request value object.
   */
  public function setRequest(McpRequest $request): void {
    $this->request = $request;
  }

}
