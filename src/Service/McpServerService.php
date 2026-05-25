<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Service;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Exception\McpException;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\PluginManager\McpToolPluginManager;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpRequest;
use Drupal\drupal_mcp\ValueObject\McpResponse;

/**
 * Protocol-level handler for MCP JSON-RPC requests.
 */
final class McpServerService {

  /**
   * Constructs the service.
   *
   * @param \Drupal\drupal_mcp\PluginManager\McpToolPluginManager $pluginManager
   *   The MCP tool plugin manager.
   * @param ToolRegistryService $toolRegistry
   *   The tool enable/disable registry.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel for the drupal_mcp module.
   */
  public function __construct(
    private readonly McpToolPluginManager $pluginManager,
    private readonly ToolRegistryService $toolRegistry,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Dispatches the request to the appropriate handler.
   *
   * Never throws — every error path is converted to an McpResponse.
   */
  public function handle(McpRequest $request): McpResponse {
    return match ($request->method) {
      'initialize' => $this->handleInitialize($request),
      'notifications/initialized' => McpResponse::success($request->id, []),
      'tools/list' => $this->handleToolsList($request),
      'tools/call' => $this->handleToolsCall($request),
      default => McpResponse::error(
        $request->id,
        McpError::METHOD_NOT_FOUND,
        sprintf("Method '%s' not found.", $request->method),
      ),
    };
  }

  /**
   * Implements the MCP "initialize" handshake.
   */
  private function handleInitialize(McpRequest $request): McpResponse {
    return McpResponse::success($request->id, [
      'protocolVersion' => '2024-11-05',
      'capabilities' => ['tools' => (object) []],
      'serverInfo' => ['name' => 'drupal_mcp', 'version' => '1.0.0'],
    ]);
  }

  /**
   * Implements the "tools/list" method.
   */
  private function handleToolsList(McpRequest $request): McpResponse {
    $tools = [];
    foreach ($this->pluginManager->getEnabledDefinitions($this->toolRegistry) as $definition) {
      $tools[] = [
        'name' => $definition->id,
        'description' => $definition->description,
        'inputSchema' => $definition->inputSchema,
      ];
    }
    return McpResponse::success($request->id, ['tools' => $tools]);
  }

  /**
   * Implements the "tools/call" method.
   */
  private function handleToolsCall(McpRequest $request): McpResponse {
    $toolId = $request->toolName;
    if ($toolId === '') {
      return McpResponse::error(
        $request->id,
        McpError::INVALID_PARAMS,
        'Missing "params.name".',
      );
    }

    if (!$this->toolRegistry->isEnabled($toolId)) {
      return McpResponse::error(
        $request->id,
        McpError::TOOL_DISABLED,
        sprintf("Tool '%s' is disabled.", $toolId),
      );
    }

    if (!$this->pluginManager->hasDefinition($toolId)) {
      return McpResponse::error(
        $request->id,
        McpError::TOOL_NOT_FOUND,
        sprintf("Tool '%s' does not exist.", $toolId),
      );
    }

    try {
      $instance = $this->pluginManager->createInstance($toolId);
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        $request->id,
        McpError::INTERNAL_ERROR,
        'Failed to instantiate tool.',
      );
    }

    if (!$instance instanceof McpToolInterface) {
      $this->logger->error(
        'MCP tool @id does not implement McpToolInterface.',
        ['@id' => $toolId],
      );
      return McpResponse::error(
        $request->id,
        McpError::INTERNAL_ERROR,
        'Invalid tool implementation.',
      );
    }

    try {
      $toolResponse = $instance->execute($request->arguments);
      return new McpResponse(
        jsonrpc: $toolResponse->jsonrpc,
        id: $request->id,
        result: $toolResponse->result,
        error: $toolResponse->error,
      );
    }
    catch (McpException $e) {
      $this->logger->warning(
        'MCP tool @id raised an exception: @message',
        ['@id' => $toolId, '@message' => $e->getMessage()],
      );
      return McpResponse::error(
        $request->id,
        McpError::INTERNAL_ERROR,
        'Tool execution failed.',
      );
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        $request->id,
        McpError::INTERNAL_ERROR,
        'Internal server error.',
      );
    }
  }

}
