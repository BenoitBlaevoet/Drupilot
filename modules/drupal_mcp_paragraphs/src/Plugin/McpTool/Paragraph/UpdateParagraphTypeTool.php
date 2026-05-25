<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph;

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
 * Updates an existing paragraph type's label or description.
 */
#[McpTool(
  id: 'paragraph_type_update',
  label: 'Update paragraph type',
  description: 'Updates the label or description of an existing Drupal paragraph type.',
  category: 'paragraph_type',
)]
final class UpdateParagraphTypeTool implements McpToolInterface {

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
          'description' => 'Machine name of the paragraph type to update.',
        ],
        'label' => [
          'type' => 'string',
          'description' => 'New human-readable label.',
        ],
        'description' => [
          'type' => 'string',
          'description' => 'New description.',
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

      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $paragraphType */
      $paragraphType = $this->entityTypeManager->getStorage('paragraphs_type')->load($machineName);

      if ($paragraphType === NULL) {
        return McpResponse::error(NULL, McpError::INVALID_PARAMS, sprintf("Paragraph type '%s' does not exist.", $machineName));
      }

      $label = $this->getOptionalString($input, 'label');
      if ($label !== NULL) {
        $paragraphType->set('label', $label);
      }

      $description = $this->getOptionalString($input, 'description');
      if ($description !== NULL) {
        $paragraphType->set('description', $description);
      }

      $paragraphType->save();

      $this->logger->info('MCP: Updated paragraph type @machine_name.', ['@machine_name' => $machineName]);

      return McpResponse::success(NULL, ['machine_name' => $machineName]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(NULL, McpError::INTERNAL_ERROR, 'Failed to save paragraph type.');
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(NULL, McpError::INTERNAL_ERROR, 'An unexpected error occurred.');
    }
  }

  /**
   * Extracts a string value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to extract.
   *
   * @return string
   *   The string value, or empty string if not found or not a string.
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
   *   The key to extract.
   *
   * @return string|null
   *   The string value, or NULL if not present or not a string.
   */
  private function getOptionalString(array $input, string $key): ?string {
    if (!array_key_exists($key, $input)) {
      return NULL;
    }
    $value = $input[$key];
    return is_string($value) ? $value : NULL;
  }

}
