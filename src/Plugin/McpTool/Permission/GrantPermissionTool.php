<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\Permission;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Grants one or more permissions to a Drupal role.
 */
#[McpTool(
  id: 'permission_grant',
  label: 'Grant permission',
  description: 'Grants one or more permissions to a Drupal role.',
  category: 'permission',
)]
final class GrantPermissionTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\user\PermissionHandlerInterface $permissionHandler
   *   The permission handler.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PermissionHandlerInterface $permissionHandler,
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
      $container->get('user.permissions'),
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
        'role_id' => [
          'type' => 'string',
          'description' => 'Machine name of the role to grant permissions to.',
        ],
        'permissions' => [
          'type' => 'array',
          'description' => 'List of permission strings to grant.',
          'items' => ['type' => 'string'],
          'minItems' => 1,
        ],
      ],
      'required' => ['role_id', 'permissions'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $roleId = $this->getString($input, 'role_id');
      $permissions = $this->getStringArray($input, 'permissions');

      $storage = $this->entityTypeManager->getStorage('user_role');

      /** @var \Drupal\user\RoleInterface|null $role */
      $role = $storage->load($roleId);

      if ($role === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Role '%s' does not exist.", $roleId),
        );
      }

      $availablePermissions = $this->permissionHandler->getPermissions();
      $invalidPermissions = [];

      foreach ($permissions as $permission) {
        if (!isset($availablePermissions[$permission])) {
          $invalidPermissions[] = $permission;
        }
      }

      if ($invalidPermissions !== []) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf(
            "Unknown permission(s): %s",
            implode(', ', $invalidPermissions),
          ),
        );
      }

      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }

      $role->save();

      $this->logger->info(
        'MCP: Granted permissions [@permissions] to role @role.',
        [
          '@permissions' => implode(', ', $permissions),
          '@role' => $roleId,
        ],
      );

      return McpResponse::success(NULL, [
        'role_id' => $roleId,
        'granted' => $permissions,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to save role.',
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
   *   The string value, or an empty string if missing or non-string.
   */
  private function getString(array $input, string $key): string {
    $value = $input[$key] ?? '';
    return is_string($value) ? $value : '';
  }

  /**
   * Extracts an array of strings from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   *
   * @return array<int, string>
   *   The string array, filtered to confirmed strings only.
   */
  private function getStringArray(array $input, string $key): array {
    $value = $input[$key] ?? [];
    if (!is_array($value)) {
      return [];
    }
    /** @var array<int, string> $filtered */
    $filtered = array_values(array_filter($value, 'is_string'));
    return $filtered;
  }

}
