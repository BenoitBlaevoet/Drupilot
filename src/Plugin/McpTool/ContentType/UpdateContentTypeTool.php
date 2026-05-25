<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\ContentType;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates an existing Drupal content type.
 */
#[McpTool(
  id: 'content_type_update',
  label: 'Update content type',
  description: 'Updates an existing Drupal content type label, description, or help text.',
  category: 'content_type',
)]
final class UpdateContentTypeTool implements McpToolInterface {

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
        'machine_name' => [
          'type' => 'string',
          'description' => 'Machine name of the content type to update.',
        ],
        'label' => [
          'type' => 'string',
          'description' => 'New human-readable label for the content type.',
        ],
        'description' => [
          'type' => 'string',
          'description' => 'New description for the content type.',
        ],
        'help' => [
          'type' => 'string',
          'description' => 'New help text shown when creating content of this type.',
        ],
      ],
      'required' => ['machine_name'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $machineName = $this->getString($input, 'machine_name');

      $storage = $this->entityTypeManager->getStorage('node_type');
      $nodeType = $storage->load($machineName);

      if ($nodeType === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Content type '%s' does not exist.", $machineName),
        );
      }

      $label = $this->getOptionalString($input, 'label');
      if ($label !== NULL) {
        $nodeType->set('name', $label);
      }

      $description = $this->getOptionalString($input, 'description');
      if ($description !== NULL) {
        $nodeType->set('description', $description);
      }

      $help = $this->getOptionalString($input, 'help');
      if ($help !== NULL) {
        $nodeType->set('help', $help);
      }

      $nodeType->save();

      $this->logger->info(
        'MCP: Updated content type @machine_name.',
        ['@machine_name' => $machineName],
      );

      $currentLabel = $nodeType->label();

      return McpResponse::success(NULL, [
        'machine_name' => $machineName,
        'label' => is_string($currentLabel) ? $currentLabel : '',
        'description' => $this->getEntityStringValue($nodeType, 'description'),
        'help' => $this->getEntityStringValue($nodeType, 'help'),
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to save content type.',
      );
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'An unexpected error occurred.',
      );
    }
  }

  /**
   * Extracts a required string value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   *
   * @return string
   *   The string value.
   */
  private function getString(array $input, string $key): string {
    $value = $input[$key] ?? '';
    return is_string($value) ? $value : '';
  }

  /**
   * Extracts an optional string value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   *
   * @return string|null
   *   The string value, or null if not present.
   */
  private function getOptionalString(array $input, string $key): ?string {
    if (!array_key_exists($key, $input)) {
      return NULL;
    }
    $value = $input[$key];
    return is_string($value) ? $value : NULL;
  }

  /**
   * Reads a string field value from a config entity safely.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity.
   * @param string $field
   *   The field name.
   *
   * @return string
   *   The field value, or an empty string if not set or not a string.
   */
  private function getEntityStringValue(ConfigEntityInterface $entity, string $field): string {
    $value = $entity->get($field);
    return is_string($value) ? $value : '';
  }

}
