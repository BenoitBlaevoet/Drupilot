<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp_field_group\Plugin\McpTool\FieldGroup;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists field groups on a bundle's form or view display.
 */
#[McpTool(
  id: 'field_group_list',
  label: 'List field groups',
  description: 'Lists all field groups on a Drupal entity bundle\'s form or view display.',
  category: 'field_group',
)]
final class ListFieldGroupsTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
        'entity_type' => ['type' => 'string', 'description' => 'Entity type machine name.'],
        'bundle' => ['type' => 'string', 'description' => 'Bundle machine name.'],
        'display_mode' => [
          'type' => 'string',
          'enum' => ['form', 'view'],
          'description' => 'Which display to list groups from.',
        ],
      ],
      'required' => ['entity_type', 'bundle', 'display_mode'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $entityType = $this->getString($input, 'entity_type');
      $bundle = $this->getString($input, 'bundle');
      $displayMode = $this->getString($input, 'display_mode');

      $storageType = $displayMode === 'form' ? 'entity_form_display' : 'entity_view_display';

      /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface|null $display */
      $display = $this->entityTypeManager->getStorage($storageType)->load($entityType . '.' . $bundle . '.default');

      if ($display === NULL) {
        return McpResponse::error(NULL, McpError::INVALID_PARAMS, 'Display not found.');
      }

      /** @var array<string, array<string, mixed>> $groups */
      $groups = $display->getThirdPartySettings('field_group') ?: [];
      $result = [];

      foreach ($groups as $groupName => $config) {
        $result[] = [
          'group_name' => $groupName,
          'label' => $config['label'] ?? '',
          'format_type' => $config['format_type'] ?? '',
          'children' => $config['children'] ?? [],
          'weight' => $config['weight'] ?? 0,
        ];
      }

      return McpResponse::success(NULL, ['groups' => $result, 'count' => count($result)]);
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(NULL, McpError::INTERNAL_ERROR, 'An unexpected error occurred.');
    }
  }

  /**
   * Extracts a string value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to extract.
   *
   * @return string
   *   The string value, or empty string if not found or not a string.
   */
  private function getString(array $input, string $key): string {
    $value = $input[$key] ?? '';
    return is_string($value) ? $value : '';
  }

}
