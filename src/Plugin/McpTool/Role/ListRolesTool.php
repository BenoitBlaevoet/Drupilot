<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\Role;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists all Drupal roles.
 */
#[McpTool(
  id: 'role_list',
  label: 'List roles',
  description: 'Lists all Drupal roles with their id, label, weight, admin flag, and granted permissions.',
  category: 'role',
)]
final class ListRolesTool implements McpToolInterface {

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
      'properties' => (object) [],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $storage = $this->entityTypeManager->getStorage('user_role');

      /** @var \Drupal\user\RoleInterface[] $roles */
      $roles = $storage->loadMultiple();

      $result = [];
      foreach ($roles as $role) {
        $label = $role->label();
        $weight = $role->get('weight');

        $result[] = [
          'machine_name' => $role->id(),
          'label' => is_string($label) ? $label : '',
          'weight' => is_int($weight) ? $weight : 0,
          'is_admin' => (bool) $role->isAdmin(),
          'permissions' => $role->getPermissions(),
        ];
      }

      return McpResponse::success(NULL, ['roles' => $result, 'items' => $result]);
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

}
