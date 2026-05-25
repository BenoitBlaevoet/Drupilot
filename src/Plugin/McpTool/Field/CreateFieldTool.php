<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\Field;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\FieldType\FieldTypeCollector;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a new field to a Drupal entity bundle.
 */
#[McpTool(
  id: 'field_create',
  label: 'Create field',
  description: 'Adds a new field to a Drupal entity bundle.',
  category: 'field',
)]
final class CreateFieldTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\drupal_mcp\FieldType\FieldTypeCollector $fieldTypeCollector
   *   Aggregated field type provider.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
    private readonly FieldTypeCollector $fieldTypeCollector,
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
    /** @var \Drupal\drupal_mcp\FieldType\FieldTypeCollector $collector */
    $collector = $container->get('drupal_mcp.field_type_collector');
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.channel.drupal_mcp'),
      $collector,
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
          'description' => 'The entity type machine name (e.g. "node", "taxonomy_term").',
        ],
        'bundle' => [
          'type' => 'string',
          'description' => 'The bundle machine name (e.g. "article").',
        ],
        'field_name' => [
          'type' => 'string',
          'description' => 'Machine name of the field. Must start with "field_".',
          'pattern' => '^field_[a-z0-9_]+$',
        ],
        'field_type' => [
          'type' => 'string',
          'description' => 'The field type plugin ID.',
          'enum' => $this->fieldTypeCollector->getSupportedTypes(),
        ],
        'label' => [
          'type' => 'string',
          'description' => 'Human-readable label for the field.',
        ],
        'required' => [
          'type' => 'boolean',
          'description' => 'Whether the field is required.',
          'default' => FALSE,
        ],
        'cardinality' => [
          'type' => 'integer',
          'description' => 'Maximum number of values. Use -1 for unlimited.',
          'default' => -1,
        ],
        'settings' => [
          'type' => 'object',
          'description' => 'Field-type-specific settings.',
        ],
      ],
      'required' => ['entity_type', 'bundle', 'field_name', 'field_type', 'label'],
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
      $fieldType = $this->getString($input, 'field_type');
      $label = $this->getString($input, 'label');
      $required = $this->getBool($input, 'required', FALSE);
      $cardinality = $this->getInt($input, 'cardinality', FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
      $settings = $this->getSettings($input);

      if (!in_array($fieldType, $this->fieldTypeCollector->getSupportedTypes(), TRUE)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Field type '%s' is not supported. Enable the corresponding drupal_mcp_* sub-module.", $fieldType),
        );
      }

      // Convention: image/file fields must not be created directly on nodes.
      // All media must go through entity_reference to a media entity.
      if ($entityType === 'node' && in_array($fieldType, ['image', 'file'], TRUE)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            "Field type '%s' cannot be added directly to a node bundle. "
            . 'Media must be handled via an entity_reference field pointing to '
            . 'the media entity type (bundles: image, document, video, '
            . 'remote_video, audio).',
            $fieldType,
          ),
        );
      }

      // Guard: field instance already exists on this bundle.
      /** @var \Drupal\field\FieldConfigInterface|null $existingConfig */
      $existingConfig = $this->entityTypeManager->getStorage('field_config')->load("$entityType.$bundle.$fieldName");
      if ($existingConfig !== NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            "Field '%s' already exists on '%s/%s'.",
            $fieldName,
            $entityType,
            $bundle,
          ),
        );
      }

      // Normalise allowed_values for list_string: [{value, label}] → {value => label}.
      if ($fieldType === 'list_string' && isset($settings['allowed_values']) && is_array($settings['allowed_values'])) {
        $normalised = [];
        foreach ($settings['allowed_values'] as $item) {
          if (is_array($item) && isset($item['value'])) {
            $normalised[(string) $item['value']] = (string) ($item['label'] ?? $item['value']);
          }
        }
        if (!empty($normalised)) {
          $settings['allowed_values'] = $normalised;
        }
      }

      // Create field storage if it does not yet exist.
      /** @var \Drupal\field\FieldStorageConfigInterface|null $fieldStorage */
      $fieldStorage = $this->entityTypeManager->getStorage('field_storage_config')->load("$entityType.$fieldName");
      if ($fieldStorage === NULL) {
        $storageValues = [
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'type' => $fieldType,
          'cardinality' => $cardinality,
        ];

        if (!empty($settings)) {
          $storageValues['settings'] = $settings;
        }

        /** @var \Drupal\field\FieldStorageConfigInterface $fieldStorage */
        $fieldStorage = $this->entityTypeManager->getStorage('field_storage_config')->create($storageValues);
        $fieldStorage->save();
      }

      // Create the field instance on the bundle.
      /** @var \Drupal\field\FieldConfigInterface $fieldConfig */
      $fieldConfig = $this->entityTypeManager->getStorage('field_config')->create([
        'field_storage' => $fieldStorage,
        'bundle' => $bundle,
        'label' => $label,
        'required' => $required,
      ]);
      $fieldConfig->save();

      // Add the field to the default view display.
      $viewDisplay = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load($entityType . '.' . $bundle . '.default');

      if ($viewDisplay === NULL) {
        /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $viewDisplay */
        $viewDisplay = $this->entityTypeManager
          ->getStorage('entity_view_display')
          ->create([
            'targetEntityType' => $entityType,
            'bundle' => $bundle,
            'mode' => 'default',
            'status' => TRUE,
          ]);
      }

      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $viewDisplay */
      $viewDisplay->setComponent($fieldName)->save();

      // Add the field to the default form display.
      $formDisplay = $this->entityTypeManager
        ->getStorage('entity_form_display')
        ->load($entityType . '.' . $bundle . '.default');

      if ($formDisplay === NULL) {
        /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $formDisplay */
        $formDisplay = $this->entityTypeManager
          ->getStorage('entity_form_display')
          ->create([
            'targetEntityType' => $entityType,
            'bundle' => $bundle,
            'mode' => 'default',
            'status' => TRUE,
          ]);
      }

      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $formDisplay */
      $formDisplay->setComponent($fieldName)->save();

      $this->logger->info(
        'MCP: Created field @field_name (@field_type) on @entity_type/@bundle.',
        [
          '@field_name' => $fieldName,
          '@field_type' => $fieldType,
          '@entity_type' => $entityType,
          '@bundle' => $bundle,
        ],
      );

      return McpResponse::success(NULL, [
        'field_name' => $fieldName,
        'field_type' => $fieldType,
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'label' => $label,
        'required' => $required,
        'cardinality' => $cardinality,
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

  /**
   * Extracts a boolean value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   * @param bool $default
   *   Default value when the key is absent.
   *
   * @return bool
   *   The boolean value.
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
   * Extracts an integer value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   * @param int $default
   *   Default value when the key is absent.
   *
   * @return int
   *   The integer value.
   */
  private function getInt(array $input, string $key, int $default): int {
    if (!array_key_exists($key, $input)) {
      return $default;
    }
    $value = $input[$key];
    return is_int($value) ? $value : (int) $value;
  }

  /**
   * Extracts the optional settings array from the input.
   *
   * @param array<string, mixed> $input
   *   The input array.
   *
   * @return array<string, mixed>
   *   The settings array, or an empty array when not provided.
   */
  private function getSettings(array $input): array {
    if (!array_key_exists('settings', $input)) {
      return [];
    }
    $value = $input['settings'];
    if (!is_array($value)) {
      return [];
    }
    /** @var array<string, mixed> $value */
    return $value;
  }

}
