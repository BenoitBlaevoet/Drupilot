<?php

declare(strict_types=1);

namespace Drupal\drupilot\ValueObject;

/**
 * Immutable JSON-RPC 2.0 error object.
 */
final readonly class McpError {

  public const int PARSE_ERROR = -32700;
  public const int INVALID_REQUEST = -32600;
  public const int METHOD_NOT_FOUND = -32601;
  public const int INVALID_PARAMS = -32602;
  public const int INTERNAL_ERROR = -32603;
  public const int TOOL_DISABLED = -32000;
  public const int TOOL_NOT_FOUND = -32001;

  /**
   * Constructs an MCP error value object.
   *
   * @param int $code
   *   The JSON-RPC error code.
   * @param string $message
   *   Short, sanitized human message — never include exception details.
   * @param array<string, mixed>|null $data
   *   Optional structured payload (e.g. field violations).
   */
  public function __construct(
    public int $code,
    public string $message,
    public ?array $data = NULL,
  ) {}

  /**
   * Serialises the error to its JSON-RPC array form.
   *
   * @return array<string, mixed>
   *   Array with `code`, `message`, and optionally `data`.
   */
  public function toArray(): array {
    $out = [
      'code' => $this->code,
      'message' => $this->message,
    ];
    if ($this->data !== NULL) {
      $out['data'] = $this->data;
    }
    return $out;
  }

}
