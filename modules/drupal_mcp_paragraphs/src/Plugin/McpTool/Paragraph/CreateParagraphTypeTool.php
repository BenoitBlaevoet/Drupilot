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
 * Creates a new paragraph type.
 */
#[McpTool(
  id: 'paragraph_type_create',
  label: 'Create paragraph type',
  description: 'Creates a new Drupal paragraph type with the given machine name and label.',
  category: 'paragraph_type',
)]
final class CreateParagraphTypeTool implements McpToolInterface {

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
          'description' => 'Machine name of the paragraph type (lowercase letters, numbers, underscores only).',
          'pattern' => '^[a-z0-9_]+$',
          'maxLength' => 32,
        ],
        'label' => [
          'type' => 'string',
          'description' => 'Human-readable label for the paragraph type.',
        ],
        'description' => [
          'type' => 'string',
          'description' => 'Optional description for the paragraph type.',
        ],
      ],
      'required' => ['machine_name', 'label'],
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

      $storage = $this->entityTypeManager->getStorage('paragraphs_type');

      if ($storage->load($machineName) !== NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Paragraph type '%s' already exists.", $machineName),
        );
      }

      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $paragraphType */
      $paragraphType = $storage->create([
        'id' => $machineName,
        'label' => $label,
      ]);

      $description = $this->getOptionalString($input, 'description');
      if ($description !== NULL) {
        $paragraphType->set('description', $description);
      }

      $paragraphType->save();

      $this->logger->info(
        'MCP: Created paragraph type @machine_name (@label).',
        ['@machine_name' => $machineName, '@label' => $label],
      );

      return McpResponse::success(NULL, [
        'machine_name' => $machineName,
        'label' => $label,
        'description' => $description ?? '',
      ]);
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
   * Extracts a required string value from the input array.
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
