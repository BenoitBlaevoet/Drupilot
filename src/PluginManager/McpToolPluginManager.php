<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\Service\ToolRegistryService;
use Drupal\drupal_mcp\ValueObject\ToolDefinition;

/**
 * Manages MCP tool plugins discovered via the McpTool PHP attribute.
 */
final class McpToolPluginManager extends DefaultPluginManager {

  /**
   * Constructs the plugin manager.
   *
   * @param \Traversable<string, string> $namespaces
   *   Iterator of registered Drupal namespaces (keyed by module name).
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend used for plugin definition discovery.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler for invoking alter hooks.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct(
      'Plugin/McpTool',
      $namespaces,
      $moduleHandler,
      McpToolInterface::class,
      McpTool::class,
    );
    $this->alterInfo('mcp_tool_info');
    $this->setCacheBackend($cacheBackend, 'mcp_tool_plugins');
  }

  /**
   * Returns the definitions of every discovered tool, as value objects.
   *
   * @return array<string, ToolDefinition>
   *   Map of tool id => ToolDefinition.
   */
  public function getToolDefinitions(): array {
    $definitions = $this->getDefinitions();
    $out = [];
    foreach ($definitions as $id => $definition) {
      if (!is_string($id)) {
        continue;
      }
      $out[$id] = $this->buildToolDefinition($id, $definition);
    }
    return $out;
  }

  /**
   * Returns only the definitions of tools currently enabled.
   *
   * @param \Drupal\drupal_mcp\Service\ToolRegistryService $registry
   *   Registry that owns enabled/disabled state.
   *
   * @return array<string, ToolDefinition>
   *   Map of tool id => ToolDefinition, filtered to enabled tools only.
   */
  public function getEnabledDefinitions(ToolRegistryService $registry): array {
    $out = [];
    foreach ($this->getToolDefinitions() as $id => $definition) {
      if (!$registry->isEnabled($id)) {
        continue;
      }
      $out[$id] = $definition;
    }
    return $out;
  }

  /**
   * Builds a ToolDefinition from a raw plugin definition.
   *
   * @param string $id
   *   The plugin id.
   * @param mixed $definition
   *   The raw definition returned by DefaultPluginManager.
   *
   * @return \Drupal\drupal_mcp\ValueObject\ToolDefinition
   *   The value-object representation.
   */
  private function buildToolDefinition(string $id, mixed $definition): ToolDefinition {
    $label = '';
    $description = '';
    $category = '';
    $inputSchema = [];

    if (is_array($definition)) {
      $label = is_string($definition['label'] ?? NULL) ? $definition['label'] : '';
      $description = is_string($definition['description'] ?? NULL) ? $definition['description'] : '';
      $category = is_string($definition['category'] ?? NULL) ? $definition['category'] : '';
    }

    try {
      $instance = $this->createInstance($id);
      if ($instance instanceof McpToolInterface) {
        $inputSchema = $instance->getInputSchema();
      }
    }
    catch (\Throwable) {
      // Discovery should not be fatal when a tool fails to instantiate; the
      // server-side handler will report errors when the tool is invoked.
      $inputSchema = [];
    }

    return new ToolDefinition(
      id: $id,
      label: $label,
      description: $description,
      category: $category,
      inputSchema: $inputSchema,
    );
  }

}
