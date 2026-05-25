<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\ContentType;

use Drupal\Core\Database\Connection;
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
 * Deletes an existing Drupal content type.
 */
#[McpTool(
  id: 'content_type_delete',
  label: 'Delete content type',
  description: 'Deletes a Drupal content type, optionally forcing deletion even when nodes exist.',
  category: 'content_type',
)]
final class DeleteContentTypeTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
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
      $container->get('database'),
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
          'description' => 'Machine name of the content type to delete.',
        ],
        'force' => [
          'type' => 'boolean',
          'description' => 'When true, delete even if nodes of this type exist.',
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
      $force = isset($input['force']) && (bool) $input['force'];

      $storage = $this->entityTypeManager->getStorage('node_type');
      $nodeType = $storage->load($machineName);

      if ($nodeType === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Content type '%s' does not exist.", $machineName),
        );
      }

      if (!$force) {
        $statement = $this->database
          ->select('node_field_data', 'n')
          ->condition('n.type', $machineName)
          ->countQuery()
          ->execute();

        $count = $statement !== NULL ? (int) $statement->fetchField() : 0;

        if ($count > 0) {
          return McpResponse::error(
            NULL,
            McpError::INVALID_PARAMS,
            sprintf(
              "Cannot delete content type '%s': %d node(s) exist. Use force=true to override.",
              $machineName,
              $count,
            ),
          );
        }
      }

      $nodeType->delete();

      $this->logger->info(
        'MCP: Deleted content type @machine_name.',
        ['@machine_name' => $machineName],
      );

      return McpResponse::success(NULL, [
        'machine_name' => $machineName,
        'deleted' => TRUE,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to delete content type.',
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
