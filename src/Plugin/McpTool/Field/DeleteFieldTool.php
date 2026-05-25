<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\Field;

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
 * Removes a field from a Drupal entity bundle.
 */
#[McpTool(
  id: 'field_delete',
  label: 'Delete field',
  description: 'Removes a field from a Drupal entity bundle.',
  category: 'field',
)]
final class DeleteFieldTool implements McpToolInterface {

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
   *   Plugin configuration array.
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
        'entity_type' => [
          'type' => 'string',
          'description' => 'The entity type machine name (e.g. "node").',
        ],
        'bundle' => [
          'type' => 'string',
          'description' => 'The bundle machine name (e.g. "article").',
        ],
        'field_name' => [
          'type' => 'string',
          'description' => 'Machine name of the field to remove.',
        ],
      ],
      'required' => ['entity_type', 'bundle', 'field_name'],
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
      $fieldName = $this->getString($input, 'field_name');

      /** @var \Drupal\field\FieldConfigInterface|null $fieldConfig */
      $fieldConfig = $this->entityTypeManager
        ->getStorage('field_config')
        ->load("$entityType.$bundle.$fieldName");

      if ($fieldConfig === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            "Field '%s' does not exist on '%s/%s'.",
            $fieldName,
            $entityType,
            $bundle,
          ),
        );
      }

      // Delete the field instance from this bundle.
      $fieldConfig->delete();

      // After deletion, check whether the storage is still used by other
      // bundles. Only delete storage when it is now orphaned.
      /** @var \Drupal\field\FieldStorageConfigInterface|null $fieldStorage */
      $fieldStorage = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->load("$entityType.$fieldName");

      if ($fieldStorage !== NULL && empty($fieldStorage->getBundles())) {
        $fieldStorage->delete();

        $this->logger->info(
          'MCP: Deleted orphaned field storage @field_name on @entity_type.',
          [
            '@field_name' => $fieldName,
            '@entity_type' => $entityType,
          ],
        );
      }

      $this->logger->info(
        'MCP: Deleted field @field_name from @entity_type/@bundle.',
        [
          '@field_name' => $fieldName,
          '@entity_type' => $entityType,
          '@bundle' => $bundle,
        ],
      );

      return McpResponse::success(NULL, [
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'deleted' => TRUE,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to delete field.',
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

}
