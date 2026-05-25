<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\Node;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupilot\Attribute\McpTool;
use Drupal\drupilot\Plugin\McpTool\McpToolInterface;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists Drupal nodes with optional filters and pagination.
 */
#[McpTool(
  id: 'node_list',
  label: 'List nodes',
  description: 'Lists Drupal nodes with optional filters and pagination.',
  category: 'node',
)]
final class ListNodesTool implements McpToolInterface {

  private const int MAX_LIMIT = 200;

  private const int DEFAULT_LIMIT = 50;

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
        'type' => [
          'type' => 'string',
          'description' => 'Filter by content type machine name.',
        ],
        'status' => [
          'type' => 'integer',
          'description' => 'Filter by published status: 1 = published, 0 = unpublished.',
          'enum' => [0, 1],
        ],
        'uid' => [
          'type' => 'integer',
          'description' => 'Filter by author user ID.',
        ],
        'limit' => [
          'type' => 'integer',
          'description' => 'Maximum number of nodes to return (max 200).',
          'default' => self::DEFAULT_LIMIT,
          'maximum' => self::MAX_LIMIT,
        ],
        'offset' => [
          'type' => 'integer',
          'description' => 'Number of nodes to skip.',
          'default' => 0,
        ],
        'sort_by' => [
          'type' => 'string',
          'description' => 'Field to sort by.',
          'enum' => ['created', 'changed', 'title'],
          'default' => 'created',
        ],
        'sort_dir' => [
          'type' => 'string',
          'description' => 'Sort direction.',
          'enum' => ['ASC', 'DESC'],
          'default' => 'DESC',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $limit = min(
        $this->getInt($input, 'limit', self::DEFAULT_LIMIT),
        self::MAX_LIMIT,
      );
      $offset = $this->getInt($input, 'offset', 0);
      $sortBy = $this->getSortBy($input);
      $sortDir = $this->getSortDir($input);

      $storage = $this->entityTypeManager->getStorage('node');
      $query = $storage->getQuery()->accessCheck(TRUE);

      $type = $this->getOptionalString($input, 'type');
      if ($type !== NULL) {
        $query->condition('type', $type);
      }

      $status = $this->getOptionalInt($input, 'status');
      if ($status !== NULL) {
        $query->condition('status', $status);
      }

      $uid = $this->getOptionalInt($input, 'uid');
      if ($uid !== NULL) {
        $query->condition('uid', $uid);
      }

      $query->sort($sortBy, $sortDir);
      $query->range($offset, $limit);

      /** @var array<int|string, int|string> $nids */
      $nids = $query->execute();

      if (empty($nids)) {
        return McpResponse::success(NULL, ['nodes' => [], 'count' => 0, 'items' => []]);
      }

      /** @var array<int|string, \Drupal\node\NodeInterface> $nodes */
      $nodes = $storage->loadMultiple(array_values($nids));

      $items = [];
      foreach ($nodes as $node) {
        $label = $node->label();
        $items[] = [
          'nid' => (int) $node->id(),
          'type' => $node->bundle(),
          'title' => is_string($label) ? $label : '',
          'status' => (int) $node->isPublished(),
          'created' => (int) $node->getCreatedTime(),
          'changed' => (int) $node->getChangedTime(),
          'uid' => (int) $node->getOwnerId(),
        ];
      }

      return McpResponse::success(NULL, [
        'nodes' => $items,
        'count' => count($items),
        'items' => $items,
      ]);
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
   * Returns the validated sort_by field name.
   *
   * @param array<string, mixed> $input
   *   The input array.
   *
   * @return string
   *   One of 'created', 'changed', 'title'.
   */
  private function getSortBy(array $input): string {
    $value = $input['sort_by'] ?? 'created';
    return match (TRUE) {
      $value === 'changed' => 'changed',
      $value === 'title' => 'title',
      default => 'created',
    };
  }

  /**
   * Returns the validated sort direction.
   *
   * @param array<string, mixed> $input
   *   The input array.
   *
   * @return string
   *   Either 'ASC' or 'DESC'.
   */
  private function getSortDir(array $input): string {
    $value = $input['sort_dir'] ?? 'DESC';
    return $value === 'ASC' ? 'ASC' : 'DESC';
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

}
