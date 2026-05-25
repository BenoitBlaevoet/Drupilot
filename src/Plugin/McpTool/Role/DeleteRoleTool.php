<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\Role;

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
 * Deletes a Drupal role by machine name.
 */
#[McpTool(
  id: 'role_delete',
  label: 'Delete role',
  description: 'Deletes a Drupal role by machine name. Cannot delete system roles.',
  category: 'role',
)]
final class DeleteRoleTool implements McpToolInterface {

  /**
   * System roles that must never be deleted.
   *
   * @var list<string>
   */
  private const PROTECTED_ROLES = ['anonymous', 'authenticated', 'administrator'];

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
          'description' => 'Machine name of the role to delete.',
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

      if (in_array($machineName, self::PROTECTED_ROLES, TRUE)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Role '%s' is a system role and cannot be deleted.", $machineName),
        );
      }

      $storage = $this->entityTypeManager->getStorage('user_role');
      $role = $storage->load($machineName);

      if ($role === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Role '%s' does not exist.", $machineName),
        );
      }

      $role->delete();

      $this->logger->info(
        'MCP: Deleted role @machine_name.',
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
        'Failed to delete role.',
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
