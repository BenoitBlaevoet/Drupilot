<?php

declare(strict_types=1);

namespace Drupal\drupilot_layout_paragraphs\Plugin\McpTool\Layout;

use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupilot\Attribute\McpTool;
use Drupal\drupilot\Plugin\McpTool\McpToolInterface;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists all registered Drupal layout plugins with their region definitions.
 */
#[McpTool(
  id: 'layout_list',
  label: 'List layouts',
  description: 'Returns all registered Drupal layout plugins with their IDs, labels, and region definitions.',
  category: 'layout',
)]
final class ListLayoutsTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layoutPluginManager
   *   The layout plugin manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
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
      $container->get('plugin.manager.core.layout'),
      $container->get('logger.channel.drupilot'),
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
        'include_regions' => [
          'type' => 'boolean',
          'description' => 'Whether to include region definitions for each layout. Defaults to true.',
          'default' => TRUE,
        ],
      ],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $includeRegions = $this->getBool($input, 'include_regions', TRUE);

      $definitions = $this->layoutPluginManager->getDefinitions();

      $layouts = [];
      foreach ($definitions as $id => $definition) {
        // Guard against non-LayoutDefinition entries from third-party plugins.
        // @phpstan-ignore-next-line instanceof.alwaysTrue
        if (!($definition instanceof LayoutDefinition)) {
          continue;
        }

        $item = [
          'id' => $id,
          'label' => (string) $definition->getLabel(),
          'category' => (string) $definition->getCategory(),
        ];

        if ($includeRegions) {
          $regions = [];
          foreach ($definition->getRegions() as $regionName => $regionData) {
            $regions[] = [
              'name' => $regionName,
              'label' => isset($regionData['label']) ? (string) $regionData['label'] : $regionName,
            ];
          }
          $item['regions'] = $regions;
        }

        $layouts[] = $item;
      }

      return McpResponse::success(NULL, [
        'items' => $layouts,
        'count' => count($layouts),
      ]);
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(NULL, McpError::INTERNAL_ERROR, 'An unexpected error occurred.');
    }
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

}
