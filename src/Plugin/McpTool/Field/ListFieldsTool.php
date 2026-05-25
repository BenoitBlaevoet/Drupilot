<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\Field;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupilot\Attribute\McpTool;
use Drupal\drupilot\Plugin\McpTool\McpToolInterface;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot\ValueObject\McpResponse;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists all configurable fields on a Drupal entity bundle.
 */
#[McpTool(
  id: 'field_list',
  label: 'List fields',
  description: 'Lists all configurable fields on a Drupal entity bundle.',
  category: 'field',
)]
final class ListFieldsTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityFieldManagerInterface $entityFieldManager,
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
      'properties' => [
        'entity_type' => [
          'type' => 'string',
          'description' => 'The entity type machine name (e.g. "node").',
        ],
        'bundle' => [
          'type' => 'string',
          'description' => 'The bundle machine name (e.g. "article").',
        ],
      ],
      'required' => ['entity_type', 'bundle'],
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

      $definitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);

      /** @var array<int, array<string, mixed>> $fields */
      $fields = [];

      foreach ($definitions as $fieldDefinition) {
        // Only include configurable fields (FieldConfig instances).
        // Base fields provided natively by the entity type are excluded.
        if (!($fieldDefinition instanceof FieldConfigInterface)) {
          continue;
        }

        $fieldStorage = $fieldDefinition->getFieldStorageDefinition();

        $fields[] = [
          'field_name' => $fieldDefinition->getName(),
          'field_type' => $fieldStorage->getType(),
          'label' => (string) $fieldDefinition->getLabel(),
          'required' => $fieldDefinition->isRequired(),
          'cardinality' => $fieldStorage->getCardinality(),
          'settings' => $fieldStorage->getSettings(),
        ];
      }

      return McpResponse::success(NULL, [
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'fields' => $fields,
        'count' => count($fields),
        'items' => $fields,
      ]);
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
