<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp_field_group\Plugin\McpTool\FieldGroup;

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
 * Deletes a field group from a bundle's form or view display.
 */
#[McpTool(
  id: 'field_group_delete',
  label: 'Delete field group',
  description: 'Removes a field group from a Drupal entity bundle\'s form or view display.',
  category: 'field_group',
)]
final class DeleteFieldGroupTool implements McpToolInterface {

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
        'entity_type' => ['type' => 'string', 'description' => 'Entity type machine name.'],
        'bundle' => ['type' => 'string', 'description' => 'Bundle machine name.'],
        'display_mode' => [
          'type' => 'string',
          'enum' => ['form', 'view'],
          'description' => 'Which display to remove the group from.',
        ],
        'group_name' => ['type' => 'string', 'description' => 'Machine name of the group to delete.'],
      ],
      'required' => ['entity_type', 'bundle', 'display_mode', 'group_name'],
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
      $displayMode = $this->getString($input, 'display_mode');
      $groupName = $this->getString($input, 'group_name');

      $storageType = $displayMode === 'form' ? 'entity_form_display' : 'entity_view_display';

      /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface|null $display */
      $display = $this->entityTypeManager->getStorage($storageType)->load($entityType . '.' . $bundle . '.default');

      if ($display === NULL) {
        return McpResponse::error(NULL, McpError::INVALID_PARAMS, 'Display not found.');
      }

      /** @var array<string, mixed> $groups */
      $groups = $display->getThirdPartySettings('field_group');
      if (!isset($groups[$groupName])) {
        return McpResponse::error(NULL, McpError::INVALID_PARAMS, sprintf("Field group '%s' does not exist.", $groupName));
      }

      $display->unsetThirdPartySetting('field_group', $groupName);
      $display->save();

      $this->logger->info('MCP: Deleted field group @group from @entity_type/@bundle (@mode).', [
        '@group' => $groupName,
        '@entity_type' => $entityType,
        '@bundle' => $bundle,
        '@mode' => $displayMode,
      ]);

      return McpResponse::success(NULL, ['group_name' => $groupName, 'deleted' => TRUE]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(NULL, McpError::INTERNAL_ERROR, 'Failed to save display.');
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

}
