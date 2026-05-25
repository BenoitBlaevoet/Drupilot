<?php

declare(strict_types=1);

namespace Drupal\drupilot\ValueObject;

/**
 * Immutable representation of an incoming MCP JSON-RPC request.
 */
final readonly class McpRequest {

  /**
   * Constructs an MCP request value object.
   *
   * @param string $jsonrpc
   *   Protocol version. Must always be "2.0".
   * @param string|int|null $id
   *   JSON-RPC correlation id (notifications use null).
   * @param string $method
   *   Invoked method (e.g. "tools/list", "tools/call").
   * @param string $toolName
   *   Tool identifier for "tools/call"; empty string otherwise.
   * @param array<string, mixed> $arguments
   *   Arguments forwarded to the tool's execute() method.
   */
  public function __construct(
    public string $jsonrpc,
    public string|int|null $id,
    public string $method,
    public string $toolName,
    public array $arguments,
  ) {}

  /**
   * Builds an MCP request from a decoded JSON-RPC payload.
   *
   * @param array<string, mixed> $data
   *   The decoded JSON-RPC envelope.
   *
   * @return self
   *   The parsed request value object.
   *
   * @throws \InvalidArgumentException
   *   When required fields are missing or malformed.
   */
  public static function fromArray(array $data): self {
    $jsonrpc = $data['jsonrpc'] ?? NULL;
    if ($jsonrpc !== '2.0') {
      throw new \InvalidArgumentException('Invalid or missing "jsonrpc" field — expected "2.0".');
    }

    $method = $data['method'] ?? NULL;
    if (!is_string($method) || $method === '') {
      throw new \InvalidArgumentException('Invalid or missing "method" field — expected non-empty string.');
    }

    $id = $data['id'] ?? NULL;
    if ($id !== NULL && !is_string($id) && !is_int($id)) {
      throw new \InvalidArgumentException('Invalid "id" field — expected string, integer, or null.');
    }

    $params = $data['params'] ?? [];
    if (!is_array($params)) {
      throw new \InvalidArgumentException('Invalid "params" field — expected object.');
    }

    $toolName = '';
    if (isset($params['name'])) {
      if (!is_string($params['name'])) {
        throw new \InvalidArgumentException('Invalid "params.name" — expected string.');
      }
      $toolName = $params['name'];
    }

    $arguments = [];
    if (isset($params['arguments'])) {
      if (!is_array($params['arguments'])) {
        throw new \InvalidArgumentException('Invalid "params.arguments" — expected object.');
      }
      /** @var array<string, mixed> $arguments */
      $arguments = $params['arguments'];
    }

    return new self(
      jsonrpc: $jsonrpc,
      id: $id,
      method: $method,
      toolName: $toolName,
      arguments: $arguments,
    );
  }

}
