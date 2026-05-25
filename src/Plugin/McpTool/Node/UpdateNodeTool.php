<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\Node;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates an existing Drupal node by node ID.
 */
#[McpTool(
  id: 'node_update',
  label: 'Update node',
  description: 'Updates an existing Drupal node by node ID.',
  category: 'node',
)]
final class UpdateNodeTool implements McpToolInterface {

  /**
   * Constructs the tool.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user account proxy.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
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
      $container->get('current_user'),
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
        'nid' => [
          'type' => 'integer',
          'description' => 'Node ID to update.',
        ],
        'title' => [
          'type' => 'string',
          'description' => 'New node title.',
        ],
        'status' => [
          'type' => 'integer',
          'description' => 'Published status: 1 = published, 0 = unpublished.',
          'enum' => [0, 1],
        ],
        'uid' => [
          'type' => 'integer',
          'description' => 'New author user ID.',
        ],
        'langcode' => [
          'type' => 'string',
          'description' => 'Language code for the node.',
        ],
        'body' => [
          'type' => 'string',
          'description' => 'Body field text (plain text or HTML).',
        ],
        'fields' => [
          'type' => 'object',
          'description' => 'Additional field values keyed by field machine name.',
        ],
        'new_revision' => [
          'type' => 'boolean',
          'description' => 'Whether to create a new revision.',
          'default' => FALSE,
        ],
        'revision_log' => [
          'type' => 'string',
          'description' => 'Revision log message (used when new_revision is true).',
        ],
      ],
      'required' => ['nid'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $nid = $this->getInt($input, 'nid', 0);

      /** @var \Drupal\node\NodeInterface|null $node */
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if ($node === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Node %d does not exist.", $nid),
        );
      }

      if (!$node->access('update', $this->currentUser)) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Access denied to update node %d.", $nid),
        );
      }

      $newRevision = $this->getBool($input, 'new_revision', FALSE);
      if ($newRevision) {
        $node->setNewRevision(TRUE);
        $revisionLog = $this->getOptionalString($input, 'revision_log');
        if ($revisionLog !== NULL) {
          $node->setRevisionLogMessage($revisionLog);
        }
      }

      $title = $this->getOptionalString($input, 'title');
      if ($title !== NULL) {
        $node->setTitle($title);
      }

      $status = $this->getOptionalInt($input, 'status');
      if ($status !== NULL) {
        $node->set('status', $status);
      }

      $uid = $this->getOptionalInt($input, 'uid');
      if ($uid !== NULL) {
        $node->set('uid', $uid);
      }

      $langcode = $this->getOptionalString($input, 'langcode');
      if ($langcode !== NULL) {
        $node->set('langcode', $langcode);
      }

      $body = $this->getOptionalString($input, 'body');
      if ($body !== NULL) {
        $node->set('body', ['value' => $body, 'format' => 'basic_html']);
      }

      $this->applyFields($node, $input);

      $node->save();

      $this->logger->info(
        'MCP: Updated node @nid.',
        ['@nid' => $nid],
      );

      return McpResponse::success(NULL, [
        'nid' => $nid,
        'title' => (string) $node->getTitle(),
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to save node.',
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
   * Applies the `fields` map to the node, skipping invalid fields with a log.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array<string, mixed> $input
   *   The tool input.
   */
  private function applyFields(NodeInterface $node, array $input): void {
    if (!isset($input['fields']) || !is_array($input['fields'])) {
      return;
    }

    // Cast to array<mixed> — the outer array<string, mixed> gives us mixed
    // for nested values; the is_array() guard above ensures it is an array.
    /** @var array<mixed> $fields */
    $fields = $input['fields'];

    foreach ($fields as $fieldName => $value) {
      if (!is_string($fieldName)) {
        continue;
      }
      try {
        $node->set($fieldName, $value);
      }
      catch (\Throwable $e) {
        $this->logger->warning(
          'MCP: Could not set field @field on node: @message',
          ['@field' => $fieldName, '@message' => $e->getMessage()],
        );
      }
    }
  }

  /**
   * Extracts an integer value from input.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   * @param int $default
   *   Default value when key is absent.
   *
   * @return int
   *   The integer value or the default.
   */
  private function getInt(array $input, string $key, int $default): int {
    $value = $input[$key] ?? $default;
    return is_int($value) ? $value : $default;
  }

  /**
   * Extracts an optional integer value from input.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   *
   * @return int|null
   *   The integer value, or null if not present.
   */
  private function getOptionalInt(array $input, string $key): ?int {
    if (!array_key_exists($key, $input)) {
      return NULL;
    }
    $value = $input[$key];
    return is_int($value) ? $value : NULL;
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
   * Extracts a boolean value from input.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   * @param bool $default
   *   Default value when key is absent.
   *
   * @return bool
   *   The boolean value or the default.
   */
  private function getBool(array $input, string $key, bool $default): bool {
    if (!array_key_exists($key, $input)) {
      return $default;
    }
    $value = $input[$key];
    if (is_bool($value)) {
      return $value;
    }
    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
  }

}
