<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Paragraph;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enables the layout_paragraphs behavior plugin on a paragraph type.
 */
#[McpTool(
  id: 'paragraph_type_configure_layout',
  label: 'Configure paragraph type for layout paragraphs',
  description: 'Enables the layout_paragraphs behavior plugin on a paragraph type and sets which layout plugins are available.',
  category: 'paragraph_type',
)]
final class ConfigureParagraphTypeLayoutTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layoutPluginManager
   *   The layout plugin manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LayoutPluginManagerInterface $layoutPluginManager,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param mixed $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    mixed $plugin_id,
    mixed $plugin_definition,
  ): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.core.layout'),
      $container->get('logger.channel.drupal_mcp'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The JSON schema definition for this tool's input.
   */
  public function getInputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'machine_name' => [
          'type' => 'string',
          'description' => 'Machine name of the paragraph type to configure.',
          'pattern' => '^[a-z0-9_]+$',
          'minLength' => 1,
          'maxLength' => 32,
        ],
        'available_layouts' => [
          'type' => 'array',
          'description' => 'List of layout plugin IDs to make available on this paragraph type.',
          'items' => ['type' => 'string'],
          'minItems' => 1,
        ],
        'enabled' => [
          'type' => 'boolean',
          'description' => 'Whether the layout_paragraphs behavior is enabled. Defaults to true.',
          'default' => TRUE,
        ],
      ],
      'required' => ['machine_name', 'available_layouts'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $machineName = $this->getString($input, 'machine_name');
      $availableLayouts = $this->getStringArray($input, 'available_layouts');
      $enabled = $this->getBool($input, 'enabled', TRUE);

      $paragraphType = $this->entityTypeManager
        ->getStorage('paragraphs_type')
        ->load($machineName);

      if (!($paragraphType instanceof ParagraphsTypeInterface)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Paragraph type '%s' does not exist.", $machineName),
        );
      }

      $allDefinitions = $this->layoutPluginManager->getDefinitions();
      $invalidLayouts = [];
      foreach ($availableLayouts as $layoutId) {
        $definition = $allDefinitions[$layoutId] ?? NULL;
        if (!($definition instanceof LayoutDefinition)) {
          $invalidLayouts[] = $layoutId;
        }
      }

      if (!empty($invalidLayouts)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            'The following layout IDs are not registered: %s.',
            implode(', ', $invalidLayouts),
          ),
        );
      }

      $existing = $paragraphType->get('behavior_plugins');
      $existing = is_array($existing) ? $existing : [];
      $existing['layout_paragraphs'] = [
        'enabled' => $enabled,
        'available_layouts' => $availableLayouts,
      ];

      $paragraphType->set('behavior_plugins', $existing);
      $paragraphType->save();

      $this->logger->info(
        'MCP: Configured layout_paragraphs behavior on paragraph type @machine_name.',
        ['@machine_name' => $machineName],
      );

      return McpResponse::success(NULL, [
        'machine_name' => $machineName,
        'enabled' => $enabled,
        'available_layouts' => $availableLayouts,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(NULL, McpError::INTERNAL_ERROR, 'Failed to save paragraph type.');
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(NULL, McpError::INTERNAL_ERROR, 'An unexpected error occurred.');
    }
  }

  /**
   * Extracts a required string value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to extract.
   *
   * @return string
   *   The string value, or empty string if absent or not a string.
   */
  private function getString(array $input, string $key): string {
    $value = $input[$key] ?? '';
    return is_string($value) ? $value : '';
  }

  /**
   * Extracts a boolean value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to extract.
   * @param bool $default
   *   Default when absent.
   *
   * @return bool
   *   The resolved boolean.
   */
  private function getBool(array $input, string $key, bool $default): bool {
    if (!array_key_exists($key, $input)) {
      return $default;
    }
    $value = $input[$key];
    if (is_bool($value)) {
      return $value;
    }
    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
  }

  /**
   * Extracts a string[] from the input array.
   *
   * Returns an empty array if the key is absent or the value is not an array.
   * Each non-string element is cast to string.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to extract.
   *
   * @return string[]
   *   The extracted string list.
   */
  private function getStringArray(array $input, string $key): array {
    if (!array_key_exists($key, $input)) {
      return [];
    }
    $raw = $input[$key];
    if (!is_array($raw)) {
      return [];
    }
    $result = [];
    foreach ($raw as $item) {
      $result[] = is_string($item) ? $item : (string) $item;
    }
    return $result;
  }

}
