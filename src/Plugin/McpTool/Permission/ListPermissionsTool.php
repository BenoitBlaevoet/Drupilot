<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\Permission;

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
 * Lists all Drupal permissions, optionally filtered by provider or role.
 */
#[McpTool(
  id: 'permission_list',
  label: 'List permissions',
  description: 'Lists all Drupal permissions, optionally filtered by provider or enriched with role grant status.',
  category: 'permission',
)]
final class ListPermissionsTool implements McpToolInterface {

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
          'description' => 'If provided, each permission entry includes a "granted" boolean indicating whether this role has the permission.',
        ],
        'provider' => [
          'type' => 'string',
          'description' => 'If provided, only permissions from this module/provider are returned.',
        ],
      ],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $roleId = $this->getOptionalString($input, 'role_id');
      $provider = $this->getOptionalString($input, 'provider');

      $allPermissions = $this->permissionHandler->getPermissions();

      if ($provider !== NULL) {
        $allPermissions = array_filter(
          $allPermissions,
          static fn(array $perm): bool => isset($perm['provider']) && $perm['provider'] === $provider,
        );
      }

      $grantedPermissions = [];
      $includeGranted = $roleId !== NULL;

      if ($includeGranted) {
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

        $grantedPermissions = array_flip($role->getPermissions());
      }

      $result = [];
      foreach ($allPermissions as $permissionName => $permissionInfo) {
        $title = $this->resolvePermissionTitle($permissionInfo);
        $permProvider = isset($permissionInfo['provider']) && is_string($permissionInfo['provider'])
          ? $permissionInfo['provider']
          : '';

        $entry = [
          'permission' => $permissionName,
          'title' => $title,
          'provider' => $permProvider,
        ];

        if ($includeGranted) {
          $entry['granted'] = isset($grantedPermissions[$permissionName]);
        }

        $result[] = $entry;
      }

      return McpResponse::success(NULL, ['permissions' => $result, 'items' => $result]);
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
   * Resolves the human-readable title from a permission info array.
   *
   * The 'title' value in Drupal permission arrays is typically a
   * TranslatableMarkup object. We cast it to string for JSON serialisation.
   *
   * @param array<string, mixed> $permissionInfo
   *   The permission definition array from PermissionHandlerInterface.
   *
   * @return string
   *   The human-readable permission title.
   */
  private function resolvePermissionTitle(array $permissionInfo): string {
    $title = $permissionInfo['title'] ?? '';
    if ($title instanceof \Stringable) {
      return (string) $title;
    }
    return is_string($title) ? $title : '';
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
   *   The string value, or null if absent or non-string.
   */
  private function getOptionalString(array $input, string $key): ?string {
    if (!array_key_exists($key, $input)) {
      return NULL;
    }
    $value = $input[$key];
    return is_string($value) ? $value : NULL;
  }

}
