<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\Field;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupilot\Attribute\McpTool;
use Drupal\drupilot\Plugin\McpTool\McpToolInterface;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates settings of an existing field on a Drupal entity bundle.
 */
#[McpTool(
  id: 'field_update',
  label: 'Update field',
  description: 'Updates settings of an existing field on a Drupal entity bundle.',
  category: 'field',
)]
final class UpdateFieldTool implements McpToolInterface {

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
        'field_name' => [
          'type' => 'string',
          'description' => 'Machine name of the field to update.',
        ],
        'label' => [
          'type' => 'string',
          'description' => 'New human-readable label for the field.',
        ],
        'required' => [
          'type' => 'boolean',
          'description' => 'Whether the field should be required.',
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

      /** @var \Drupal\Core\Field\FieldConfigInterface|null $fieldConfig */
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

      $updated = [];

      if (array_key_exists('label', $input)) {
        $label = $input['label'];
        if (is_string($label)) {
          $fieldConfig->setLabel($label);
          $updated[] = 'label';
        }
      }

      if (array_key_exists('required', $input)) {
        $required = $input['required'];
        $fieldConfig->setRequired(is_bool($required) ? $required : (bool) $required);
        $updated[] = 'required';
      }

      $fieldConfig->save();

      $this->logger->info(
        'MCP: Updated field @field_name on @entity_type/@bundle. Changed: @changed.',
        [
          '@field_name' => $fieldName,
          '@entity_type' => $entityType,
          '@bundle' => $bundle,
          '@changed' => implode(', ', $updated),
        ],
      );

      return McpResponse::success(NULL, [
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'label' => (string) $fieldConfig->getLabel(),
        'required' => $fieldConfig->isRequired(),
        'updated_properties' => $updated,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to save field.',
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
