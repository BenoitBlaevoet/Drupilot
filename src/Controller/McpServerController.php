<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\drupal_mcp\Service\McpServerService;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpRequest;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP entry point for the MCP server.
 *
 * Contains no business logic: parses JSON, delegates to McpServerService,
 * serialises the response.
 */
final class McpServerController implements ContainerInjectionInterface {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly McpServerService $mcpServerService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var \Drupal\drupal_mcp\Service\McpServerService $service */
    $service = $container->get('drupal_mcp.server');
    return new static($service);
  }

  /**
   * Handles a POST /mcp/v1 request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON-RPC 2.0 response.
   */
  public function handle(Request $request): JsonResponse {
    $body = (string) $request->getContent();

    try {
      /** @var mixed $decoded */
      $decoded = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return new JsonResponse(McpResponse::error(
        NULL,
        McpError::PARSE_ERROR,
        'Invalid JSON payload.',
      )->toArray());
    }

    if (!is_array($decoded)) {
      return new JsonResponse(McpResponse::error(
        NULL,
        McpError::INVALID_REQUEST,
        'Request body must be a JSON object.',
      )->toArray());
    }

    try {
      /** @var array<string, mixed> $decoded */
      $mcpRequest = McpRequest::fromArray($decoded);
    }
    catch (\InvalidArgumentException $e) {
      $id = NULL;
      if (isset($decoded['id']) && (is_string($decoded['id']) || is_int($decoded['id']))) {
        $id = $decoded['id'];
      }
      return new JsonResponse(McpResponse::error(
        $id,
        McpError::INVALID_REQUEST,
        $e->getMessage(),
      )->toArray());
    }

    $response = $this->mcpServerService->handle($mcpRequest);
    return new JsonResponse($response->toArray());
  }

}
