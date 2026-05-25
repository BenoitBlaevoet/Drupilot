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
 * Updates an existing Drupal user account.
 */
#[McpTool(
  id: 'user_update',
  label: 'Update user',
  description: 'Updates an existing Drupal user account.',
  category: 'user',
)]
final class UpdateUserTool implements McpToolInterface {

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
          'description' => 'The user ID of the account to update.',
        ],
        'name' => [
          'type' => 'string',
          'description' => 'New username.',
        ],
        'mail' => [
          'type' => 'string',
          'format' => 'email',
          'description' => 'New email address.',
        ],
        'password' => [
          'type' => 'string',
          'description' => 'New plain-text password.',
        ],
        'status' => [
          'type' => 'integer',
          'enum' => [0, 1],
          'description' => '1 to activate the account, 0 to block it.',
        ],
        'language' => [
          'type' => 'string',
          'description' => 'New preferred language code.',
        ],
        'roles_add' => [
          'type' => 'array',
          'items' => ['type' => 'string'],
          'description' => 'Role IDs to add to the user.',
        ],
        'roles_remove' => [
          'type' => 'array',
          'items' => ['type' => 'string'],
          'description' => 'Role IDs to remove from the user.',
        ],
      ],
      'required' => ['uid'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $uid = $this->getInt($input, 'uid');
      if ($uid === 0) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          'A valid uid is required.',
        );
      }

      $storage = $this->entityTypeManager->getStorage('user');
      $user = $storage->load($uid);

      if (!$user instanceof UserInterface) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("User with uid '%d' not found.", $uid),
        );
      }

      if (array_key_exists('name', $input)) {
        $name = $input['name'];
        if (is_string($name)) {
          $user->setUsername($name);
        }
      }

      if (array_key_exists('mail', $input)) {
        $mail = $input['mail'];
        if (is_string($mail)) {
          $user->setEmail($mail);
        }
      }

      if (array_key_exists('password', $input)) {
        $password = $input['password'];
        // Never log the password value.
        if (is_string($password) && $password !== '') {
          $user->setPassword($password);
        }
      }

      if (array_key_exists('status', $input)) {
        $status = $input['status'];
        if (is_int($status)) {
          $user->set('status', $status);
        }
      }

      if (array_key_exists('language', $input)) {
        $language = $input['language'];
        if (is_string($language)) {
          $user->set('langcode', $language);
          $user->set('preferred_langcode', $language);
        }
      }

      foreach ($this->getRoleIds($input, 'roles_add') as $roleId) {
        $user->addRole($roleId);
      }

      foreach ($this->getRoleIds($input, 'roles_remove') as $roleId) {
        $user->removeRole($roleId);
      }

      $user->save();

      $label = $user->label();
      $name = is_string($label) ? $label : '';

      $this->logger->info(
        'MCP: Updated user uid @uid.',
        ['@uid' => $uid],
      );

      return McpResponse::success(NULL, [
        'uid' => $uid,
        'name' => $name,
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

  /**
   * Extracts an array of role IDs from a named key in the input.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up (e.g. 'roles_add' or 'roles_remove').
   *
   * @return array<int, string>
   *   An array of role ID strings.
   */
  private function getRoleIds(array $input, string $key): array {
    if (!array_key_exists($key, $input)) {
      return [];
    }
    $roles = $input[$key];
    if (!is_array($roles)) {
      return [];
    }
    $result = [];
    foreach ($roles as $role) {
      if (is_string($role) && $role !== '') {
        $result[] = $role;
      }
    }
    return $result;
  }

}
