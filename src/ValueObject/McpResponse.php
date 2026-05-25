<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\ValueObject;

/**
 * Immutable JSON-RPC 2.0 response envelope.
 *
 * Exactly one of $result or $error is populated.
 */
final readonly class McpResponse {

  /**
   * Constructs an MCP response value object.
   *
   * @param string $jsonrpc
   *   Always "2.0".
   * @param string|int|null $id
   *   Correlation id mirrored from the request.
   * @param array<string, mixed>|null $result
   *   Success payload, or null for error responses.
   * @param \Drupal\drupal_mcp\ValueObject\McpError|null $error
   *   Error object, or null for success responses.
   */
  public function __construct(
    public string $jsonrpc,
    public string|int|null $id,
    public ?array $result,
    public ?McpError $error,
  ) {}

  /**
   * Builds a success response.
   *
   * @param string|int|null $id
   *   Correlation id from the originating request.
   * @param array<string, mixed> $result
   *   Result payload.
   *
   * @return self
   *   The response value object.
   */
  public static function success(string|int|null $id, array $result): self {
    return new self(
      jsonrpc: '2.0',
      id: $id,
      result: $result,
      error: NULL,
    );
  }

  /**
   * Builds an error response.
   *
   * @param string|int|null $id
   *   Correlation id from the originating request, or null when unavailable.
   * @param int $code
   *   JSON-RPC error code (see \Drupal\drupal_mcp\ValueObject\McpError).
   * @param string $message
   *   Sanitized human-readable message — never include stack traces.
   * @param array<string, mixed>|null $data
   *   Optional structured error data.
   *
   * @return self
   *   The response value object.
   */
  public static function error(
    string|int|null $id,
    int $code,
    string $message,
    ?array $data = NULL,
  ): self {
    return new self(
      jsonrpc: '2.0',
      id: $id,
      result: NULL,
      error: new McpError($code, $message, $data),
    );
  }

  /**
   * Serialises the response to its JSON-RPC array form.
   *
   * @return array<string, mixed>
   *   Wire-format array suitable for json_encode().
   */
  public function toArray(): array {
    $out = [
      'jsonrpc' => $this->jsonrpc,
      'id' => $this->id,
    ];

    if ($this->error !== NULL) {
      $out['error'] = $this->error->toArray();
      return $out;
    }

    $out['result'] = $this->result ?? [];
    return $out;
  }

}
