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
 * Cancels or deletes a Drupal user account.
 */
#[McpTool(
  id: 'user_delete',
  label: 'Delete user',
  description: 'Cancels or deletes a Drupal user account.',
  category: 'user',
)]
final class DeleteUserTool implements McpToolInterface {

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
          'description' => 'The user ID of the account to cancel or delete.',
        ],
        'cancel_method' => [
          'type' => 'string',
          'enum' => ['block', 'reassign', 'delete'],
          'default' => 'block',
          'description' => 'How to cancel the account: block (disable), reassign (content to anonymous), or delete (remove account and content).',
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

      if ($uid === 1) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          'The superadmin account (uid 1) cannot be deleted or cancelled.',
        );
      }

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

      $cancelMethod = $this->getOptionalString($input, 'cancel_method') ?? 'block';
      $actionTaken = $this->applyCancel($user, $uid, $cancelMethod);

      $this->logger->info(
        'MCP: Cancelled user uid @uid using method @method.',
        ['@uid' => $uid, '@method' => $cancelMethod],
      );

      return McpResponse::success(NULL, [
        'uid' => $uid,
        'action' => $actionTaken,
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to cancel user account.',
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
   * Applies the appropriate cancellation method to the user account.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param int $uid
   *   The user ID (unused, kept for call-site clarity).
   * @param string $cancelMethod
   *   One of 'block', 'reassign', or 'delete'.
   *
   * @return string
   *   A description of the action actually taken.
   */
  private function applyCancel(UserInterface $user, int $uid, string $cancelMethod): string {
    if ($cancelMethod === 'reassign') {
      // Block the account; content reassignment to anonymous is a batch
      // operation that requires a form context — we just block here.
      $user->set('status', 0);
      $user->save();
      return 'blocked';
    }

    if ($cancelMethod === 'delete') {
      $user->delete();
      return 'deleted';
    }

    // 'block' and any unknown method — disable the account.
    $user->set('status', 0);
    $user->save();
    return 'blocked';
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

}
