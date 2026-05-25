<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Field;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Switches an entity_reference_revisions field's widget and formatter to layout_paragraphs.
 */
#[McpTool(
  id: 'paragraph_field_configure_layout_display',
  label: 'Configure layout paragraphs field display',
  description: 'Switches an entity_reference_revisions field to use the layout_paragraphs widget (form display) and formatter (view display).',
  category: 'field',
)]
final class ConfigureLayoutParagraphsDisplayTool implements McpToolInterface {

  private const WIDGET_TYPE = 'layout_paragraphs';

  private const FORMATTER_TYPE = 'layout_paragraphs';

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
          'minLength' => 1,
        ],
        'bundle' => [
          'type' => 'string',
          'description' => 'The bundle machine name (e.g. "article").',
          'minLength' => 1,
        ],
        'field_name' => [
          'type' => 'string',
          'description' => 'Machine name of the entity_reference_revisions field.',
          'minLength' => 1,
        ],
        'nesting_depth' => [
          'type' => 'integer',
          'description' => 'Maximum nesting depth for layouts (0–5). 0 means no nesting.',
          'minimum' => 0,
          'maximum' => 5,
          'default' => 0,
        ],
        'require_layouts' => [
          'type' => 'boolean',
          'description' => 'Whether paragraphs must be placed inside a layout.',
          'default' => FALSE,
        ],
        'form_mode' => [
          'type' => 'string',
          'description' => 'The form display mode to configure (default: "default").',
          'default' => 'default',
        ],
        'view_mode' => [
          'type' => 'string',
          'description' => 'The view display mode to configure (default: "default").',
          'default' => 'default',
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
      $nestingDepth = $this->getInt($input, 'nesting_depth', 0);
      $requireLayouts = $this->getBool($input, 'require_layouts', FALSE);
      $rawFormMode = $this->getString($input, 'form_mode');
      $rawViewMode = $this->getString($input, 'view_mode');
      $formMode = $rawFormMode !== '' ? $rawFormMode : 'default';
      $viewMode = $rawViewMode !== '' ? $rawViewMode : 'default';

      $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);

      if (!isset($fieldDefinitions[$fieldName])) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Field '%s' does not exist on '%s/%s'.", $fieldName, $entityType, $bundle),
        );
      }

      $fieldDefinition = $fieldDefinitions[$fieldName];

      if (!($fieldDefinition instanceof FieldConfigInterface)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            "Field '%s' is not a configurable field. Only configurable fields can use the layout_paragraphs widget.",
            $fieldName,
          ),
        );
      }

      if ($fieldDefinition->getFieldStorageDefinition()->getType() !== 'entity_reference_revisions') {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            "Field '%s' is of type '%s'. Only 'entity_reference_revisions' fields can use the layout_paragraphs widget.",
            $fieldName,
            $fieldDefinition->getFieldStorageDefinition()->getType(),
          ),
        );
      }

      $formDisplayId = $entityType . '.' . $bundle . '.' . $formMode;
      $viewDisplayId = $entityType . '.' . $bundle . '.' . $viewMode;

      $formDisplay = $this->entityTypeManager
        ->getStorage('entity_form_display')
        ->load($formDisplayId);

      if (!($formDisplay instanceof EntityFormDisplayInterface)) {
        /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $formDisplay */
        $formDisplay = $this->entityTypeManager
          ->getStorage('entity_form_display')
          ->create([
            'targetEntityType' => $entityType,
            'bundle' => $bundle,
            'mode' => $formMode,
            'status' => TRUE,
          ]);
      }

      $formDisplay->setComponent($fieldName, [
        'type' => self::WIDGET_TYPE,
        'settings' => [
          'view_mode' => 'default',
          'preview_view_mode' => 'default',
          'form_display_mode' => 'default',
          'nesting_depth' => $nestingDepth,
          'require_layouts' => $requireLayouts,
          'empty_message' => '',
        ],
      ]);
      $formDisplay->save();

      $viewDisplay = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load($viewDisplayId);

      if (!($viewDisplay instanceof EntityViewDisplayInterface)) {
        /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $viewDisplay */
        $viewDisplay = $this->entityTypeManager
          ->getStorage('entity_view_display')
          ->create([
            'targetEntityType' => $entityType,
            'bundle' => $bundle,
            'mode' => $viewMode,
            'status' => TRUE,
          ]);
      }

      $viewDisplay->setComponent($fieldName, [
        'type' => self::FORMATTER_TYPE,
        'settings' => [
          'view_mode' => 'default',
          'link' => '',
        ],
      ]);
      $viewDisplay->save();

      $this->logger->info(
        'MCP: Configured layout_paragraphs widget/formatter for @field_name on @entity_type/@bundle.',
        [
          '@field_name' => $fieldName,
          '@entity_type' => $entityType,
          '@bundle' => $bundle,
        ],
      );

      return McpResponse::success(NULL, [
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'field_name' => $fieldName,
        'widget_type' => self::WIDGET_TYPE,
        'formatter_type' => self::FORMATTER_TYPE,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(NULL, McpError::INTERNAL_ERROR, 'Failed to save display configuration.');
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
   * Extracts an integer value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to extract.
   * @param int $default
   *   Default when absent.
   *
   * @return int
   *   The resolved integer.
   */
  private function getInt(array $input, string $key, int $default): int {
    if (!array_key_exists($key, $input)) {
      return $default;
    }
    $value = $input[$key];
    return is_int($value) ? $value : (int) $value;
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
