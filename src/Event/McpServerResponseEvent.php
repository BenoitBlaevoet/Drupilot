<?php

declare(strict_types=1);

namespace Drupal\drupilot\Event;

use Drupal\drupilot\ValueObject\McpRequest;
use Drupal\drupilot\ValueObject\McpResponse;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after an MCP request has been handled.
 *
 * Subscribers may inspect or replace the response value object before it is
 * serialised to JSON.
 */
final class McpServerResponseEvent extends Event {

  public const string EVENT_NAME = 'drupilot.server_response';

  /**
   * Constructs the event.
   */
  public function __construct(
    private readonly McpRequest $request,
    private McpResponse $response,
  ) {}

  /**
   * Returns the originating request.
   */
  public function getRequest(): McpRequest {
    return $this->request;
  }

  /**
   * Returns the response.
   */
  public function getResponse(): McpResponse {
    return $this->response;
  }

  /**
   * Replaces the response value object.
   */
  public function setResponse(McpResponse $response): void {
    $this->response = $response;
  }

}
