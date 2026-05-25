<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\User;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Grants or revokes a role on a Drupal user account.
 */
#[McpTool(
  id: 'user_assign_role',
  label: 'Assign role to user',
  description: 'Grants or revokes a role on a Drupal user account.',
  category: 'user',
)]
final class AssignRoleTool implements McpToolInterface {

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
        'uid' => [
          'type' => 'integer',
          'description' => 'The user ID of the account to modify.',
        ],
        'role' => [
          'type' => 'string',
          'description' => 'Machine name of the role to grant or revoke.',
        ],
        'action' => [
          'type' => 'string',
          'enum' => ['grant', 'revoke'],
          'description' => "Use 'grant' to add the role, 'revoke' to remove it.",
        ],
      ],
      'required' => ['uid', 'role', 'action'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $uid = $this->getInt($input, 'uid');
      $roleId = $this->getString($input, 'role');
      $action = $this->getString($input, 'action');

      if ($uid === 0) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          'A valid uid is required.',
        );
      }

      if ($roleId === '') {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          'A role machine name is required.',
        );
      }

      if ($action !== 'grant' && $action !== 'revoke') {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          "Action must be 'grant' or 'revoke'.",
        );
      }

      $userStorage = $this->entityTypeManager->getStorage('user');
      $user = $userStorage->load($uid);

      if (!$user instanceof UserInterface) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("User with uid '%d' not found.", $uid),
        );
      }

      $roleStorage = $this->entityTypeManager->getStorage('user_role');
      $role = $roleStorage->load($roleId);

      if ($role === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Role '%s' does not exist.", $roleId),
        );
      }

      if ($action === 'grant') {
        $user->addRole($roleId);
      }
      else {
        $user->removeRole($roleId);
      }

      $user->save();

      $label = $user->label();
      $name = is_string($label) ? $label : '';

      $this->logger->info(
        'MCP: @action role @role on user uid @uid.',
        ['@action' => ucfirst($action) . 'ed', '@role' => $roleId, '@uid' => $uid],
      );

      return McpResponse::success(NULL, [
        'uid' => $uid,
        'name' => $name,
        'role_id' => $roleId,
        'action' => $action,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to save user account.',
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
   *   The string value, or an empty string if absent or not a string.
   */
  private function getString(array $input, string $key): string {
    $value = $input[$key] ?? '';
    return is_string($value) ? $value : '';
  }

  /**
   * Extracts an integer value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   *
   * @return int
   *   The integer value, or 0 if absent or not an integer.
   */
  private function getInt(array $input, string $key): int {
    $value = $input[$key] ?? 0;
    return is_int($value) ? $value : 0;
  }

}
