<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\Permission;

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
 * Revokes one or more permissions from a Drupal role.
 */
#[McpTool(
  id: 'permission_revoke',
  label: 'Revoke permission',
  description: 'Revokes one or more permissions from a Drupal role.',
  category: 'permission',
)]
final class RevokePermissionTool implements McpToolInterface {

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
        'role_id' => [
          'type' => 'string',
          'description' => 'Machine name of the role to revoke permissions from.',
        ],
        'permissions' => [
          'type' => 'array',
          'description' => 'List of permission strings to revoke.',
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

      foreach ($permissions as $permission) {
        $role->revokePermission($permission);
      }

      $role->save();

      $this->logger->info(
        'MCP: Revoked permissions [@permissions] from role @role.',
        [
          '@permissions' => implode(', ', $permissions),
          '@role' => $roleId,
        ],
      );

      return McpResponse::success(NULL, [
        'role_id' => $roleId,
        'revoked' => $permissions,
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
