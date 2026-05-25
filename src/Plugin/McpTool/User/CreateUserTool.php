<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\User;

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
 * Creates a new Drupal user account.
 */
#[McpTool(
  id: 'user_create',
  label: 'Create user',
  description: 'Creates a new Drupal user account.',
  category: 'user',
)]
final class CreateUserTool implements McpToolInterface {

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
        'name' => [
          'type' => 'string',
          'description' => 'The username for the new account.',
        ],
        'mail' => [
          'type' => 'string',
          'format' => 'email',
          'description' => 'The email address for the new account.',
        ],
        'password' => [
          'type' => 'string',
          'description' => 'Plain-text password for the new account.',
        ],
        'status' => [
          'type' => 'integer',
          'enum' => [0, 1],
          'default' => 1,
          'description' => '1 to activate the account, 0 to block it.',
        ],
        'roles' => [
          'type' => 'array',
          'items' => ['type' => 'string'],
          'description' => 'List of role IDs to assign to the user.',
        ],
        'language' => [
          'type' => 'string',
          'default' => 'en',
          'description' => 'Preferred language code for the user.',
        ],
      ],
      'required' => ['name', 'mail', 'password'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $name = $this->getString($input, 'name');
      $mail = $this->getString($input, 'mail');
      // Password is handled via setPassword() and never logged or returned.
      $password = $this->getString($input, 'password');
      $status = $this->getInt($input, 'status', 1);
      $language = $this->getOptionalString($input, 'language') ?? 'en';

      $storage = $this->entityTypeManager->getStorage('user');

      $existing = $storage->loadByProperties(['name' => $name]);
      if (!empty($existing)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("A user with the name '%s' already exists.", $name),
        );
      }

      /** @var \Drupal\user\UserInterface $user */
      $user = $storage->create([
        'name' => $name,
        'mail' => $mail,
        'status' => $status,
        'langcode' => $language,
        'preferred_langcode' => $language,
      ]);

      $user->setPassword($password);
      $user->save();

      $roles = $this->getRoleIds($input);
      if (!empty($roles)) {
        foreach ($roles as $roleId) {
          $user->addRole($roleId);
        }
        $user->save();
      }

      $uid = (int) $user->id();

      $this->logger->info(
        'MCP: Created user @name (uid: @uid).',
        ['@name' => $name, '@uid' => $uid],
      );

      return McpResponse::success(NULL, [
        'uid' => $uid,
        'name' => $name,
        'mail' => $mail,
        'status' => $status,
        'roles' => $roles,
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
   *   The string value.
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
   *   The key to look up.
   *
   * @return string|null
   *   The string value, or null if not present.
   */
  private function getOptionalString(array $input, string $key): ?string {
    if (!array_key_exists($key, $input)) {
      return NULL;
    }
    $value = $input[$key];
    return is_string($value) ? $value : NULL;
  }

  /**
   * Extracts an integer value from the input array, with a default fallback.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   * @param int $default
   *   The default value if the key is absent.
   *
   * @return int
   *   The integer value.
   */
  private function getInt(array $input, string $key, int $default): int {
    if (!array_key_exists($key, $input)) {
      return $default;
    }
    $value = $input[$key];
    return is_int($value) ? $value : $default;
  }

  /**
   * Extracts an array of role IDs from the input.
   *
   * @param array<string, mixed> $input
   *   The input array.
   *
   * @return array<int, string>
   *   An array of role ID strings.
   */
  private function getRoleIds(array $input): array {
    if (!array_key_exists('roles', $input)) {
      return [];
    }
    $roles = $input['roles'];
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
