<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\MediaType;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupilot\Attribute\McpTool;
use Drupal\drupilot\Plugin\McpTool\McpToolInterface;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot\ValueObject\McpResponse;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a new custom Drupal media type.
 */
#[McpTool(
  id: 'media_type_create',
  label: 'Create media type',
  description: 'Creates a custom Drupal media type with the specified source plugin.',
  category: 'media_type',
)]
final class CreateMediaTypeTool implements McpToolInterface {

  /**
   * Core media bundle machine names that must never be overwritten.
   *
   * @var list<string>
   */
  private const CORE_BUNDLES = ['image', 'document', 'video', 'remote_video', 'audio'];

  /**
   * Valid media source plugin IDs.
   *
   * @var list<string>
   */
  private const VALID_SOURCES = ['image', 'file', 'audio_file', 'video_file', 'oembed:video'];

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
        'machine_name' => [
          'type' => 'string',
          'description' => 'Machine name of the media type (lowercase letter, then lowercase letters/digits/underscores, max 32 chars). Cannot be a core bundle: image, document, video, remote_video, audio.',
          'pattern' => '^[a-z][a-z0-9_]{0,30}$',
          'maxLength' => 32,
        ],
        'label' => [
          'type' => 'string',
          'description' => 'Human-readable label for the media type.',
        ],
        'description' => [
          'type' => 'string',
          'description' => 'Optional description for the media type.',
        ],
        'source' => [
          'type' => 'string',
          'description' => 'Media source plugin ID. Allowed values: image, file, audio_file, video_file, oembed:video.',
          'enum' => ['image', 'file', 'audio_file', 'video_file', 'oembed:video'],
        ],
      ],
      'required' => ['machine_name', 'label', 'source'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $machineName = $this->getString($input, 'machine_name');
      $label = $this->getString($input, 'label');
      $source = $this->getString($input, 'source');
      $description = $this->getOptionalString($input, 'description') ?? '';

      if (in_array($machineName, self::CORE_BUNDLES, TRUE)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          'Cannot overwrite a core media bundle.',
        );
      }

      $storage = $this->entityTypeManager->getStorage('media_type');
      $existing = $storage->load($machineName);

      if ($existing !== NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Media type '%s' already exists.", $machineName),
        );
      }

      if (!in_array($source, self::VALID_SOURCES, TRUE)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            "Invalid source plugin '%s'. Valid values: %s.",
            $source,
            implode(', ', self::VALID_SOURCES),
          ),
        );
      }

      /** @var \Drupal\media\MediaTypeInterface $mediaType */
      $mediaType = $storage->create([
        'id' => $machineName,
        'label' => $label,
        'description' => $description,
        'source' => $source,
      ]);
      $mediaType->save();

      $sourcePlugin = $mediaType->getSource();
      $sourceFieldRaw = $sourcePlugin->createSourceField($mediaType);

      $fieldStorage = $sourceFieldRaw->getFieldStorageDefinition();
      if ($fieldStorage instanceof FieldStorageConfig) {
        $fieldStorage->save();
      }
      if ($sourceFieldRaw instanceof FieldConfig) {
        $sourceFieldRaw->save();
      }

      $sourceFieldDefinition = $sourcePlugin->getSourceFieldDefinition($mediaType);
      $sourceFieldName = $sourceFieldDefinition !== NULL ? $sourceFieldDefinition->getName() : '';

      $mediaType->set('field_map', ['title' => $sourceFieldName]);
      $mediaType->save();

      $this->logger->info(
        'MCP: Created media type @machine_name (@label) with source @source.',
        [
          '@machine_name' => $machineName,
          '@label' => $label,
          '@source' => $source,
        ],
      );

      return McpResponse::success(NULL, [
        'machine_name' => $machineName,
        'label' => $label,
        'description' => $description,
        'source' => $source,
        'source_field_name' => $sourceFieldName,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to save media type.',
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

}
