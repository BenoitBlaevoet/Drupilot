<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\Taxonomy;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes a Drupal taxonomy vocabulary.
 */
#[McpTool(
  id: 'vocabulary_delete',
  label: 'Delete vocabulary',
  description: 'Deletes an existing Drupal taxonomy vocabulary.',
  category: 'taxonomy',
)]
final class DeleteVocabularyTool implements McpToolInterface {

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
          'description' => 'Machine name of the vocabulary to delete.',
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

      $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      $vocabulary = $storage->load($machineName);

      if (!$vocabulary instanceof VocabularyInterface) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Vocabulary '%s' does not exist.", $machineName),
        );
      }

      $vocabulary->delete();

      $this->logger->info(
        'MCP: Deleted taxonomy vocabulary @machine_name.',
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
        'Failed to delete taxonomy vocabulary.',
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
