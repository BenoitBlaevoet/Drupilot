<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\MediaType;

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
 * Deletes a custom media type.
 */
#[McpTool(
  id: 'media_type_delete',
  label: 'Delete media type',
  description: 'Deletes a custom media type and optionally its associated media entities.',
  category: 'media_type',
)]
final class DeleteMediaTypeTool implements McpToolInterface {

  /**
   * Core media bundle machine names that must never be deleted.
   *
   * @var list<string>
   */
  private const CORE_BUNDLES = ['image', 'document', 'video', 'remote_video', 'audio'];

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
        'machine_name' => [
          'type' => 'string',
          'description' => 'Machine name of the custom media type to delete. Core bundles (image, document, video, remote_video, audio) cannot be deleted.',
        ],
        'force' => [
          'type' => 'boolean',
          'description' => 'If true, also delete all media entities of this bundle before deleting the type. Defaults to false.',
          'default' => FALSE,
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
      $force = $this->getBool($input, 'force');

      if (in_array($machineName, self::CORE_BUNDLES, TRUE)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          'Core media bundles cannot be deleted.',
        );
      }

      $storage = $this->entityTypeManager->getStorage('media_type');

      /** @var \Drupal\media\MediaTypeInterface|null $mediaType */
      $mediaType = $storage->load($machineName);

      if ($mediaType === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Media type '%s' not found.", $machineName),
        );
      }

      $mediaStorage = $this->entityTypeManager->getStorage('media');

      $ids = $mediaStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', $machineName)
        ->execute();

      $count = count($ids);

      if (!$force && $count > 0) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            '%d media entities use this bundle. Use force: true to delete them.',
            $count,
          ),
        );
      }

      $deletedCount = 0;
      if ($force && $count > 0) {
        $mediaEntities = $mediaStorage->loadMultiple(array_values($ids));
        $mediaStorage->delete($mediaEntities);
        $deletedCount = $count;
      }

      $mediaType->delete();

      $this->logger->info(
        'MCP: Deleted media type @machine_name (deleted @count media entities).',
        ['@machine_name' => $machineName, '@count' => $deletedCount],
      );

      return McpResponse::success(NULL, [
        'machine_name' => $machineName,
        'deleted' => TRUE,
        'deleted_media_count' => $deletedCount,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to delete media type.',
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
   * Extracts a boolean value from the input array, defaulting to false.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   *
   * @return bool
   *   The boolean value, or false if not present.
   */
  private function getBool(array $input, string $key, bool $default = FALSE): bool {
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
