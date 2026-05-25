<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\ContentType;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupilot\Attribute\McpTool;
use Drupal\drupilot\Plugin\McpTool\McpToolInterface;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists all available Drupal content types.
 */
#[McpTool(
  id: 'content_type_list',
  label: 'List content types',
  description: 'Lists all available Drupal content types with their metadata.',
  category: 'content_type',
)]
final class ListContentTypesTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
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
      $container->get('entity_field.manager'),
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
      'properties' => (object) [],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $storage = $this->entityTypeManager->getStorage('node_type');

      /** @var \Drupal\node\NodeTypeInterface[] $nodeTypes */
      $nodeTypes = $storage->loadMultiple();

      $types = [];
      foreach ($nodeTypes as $nodeType) {
        $machineName = $nodeType->id();
        if (!is_string($machineName)) {
          continue;
        }

        $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $machineName);
        $fieldCount = count($fieldDefinitions);

        $labelValue = $nodeType->label();
        $descriptionValue = $nodeType->get('description');

        $types[] = [
          'machine_name' => $machineName,
          'label' => is_string($labelValue) ? $labelValue : '',
          'description' => is_string($descriptionValue) ? $descriptionValue : '',
          'field_count' => $fieldCount,
        ];
      }

      $this->logger->info('MCP: Listed @count content type(s).', ['@count' => count($types)]);

      return McpResponse::success(NULL, ['content_types' => $types, 'items' => $types]);
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

}
