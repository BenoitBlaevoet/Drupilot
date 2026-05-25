<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\Taxonomy;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates an existing Drupal taxonomy term.
 */
#[McpTool(
  id: 'term_update',
  label: 'Update term',
  description: 'Updates an existing Drupal taxonomy term.',
  category: 'taxonomy',
)]
final class UpdateTermTool implements McpToolInterface {

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
        'tid' => [
          'type' => 'integer',
          'description' => 'Term ID of the term to update.',
          'minimum' => 1,
        ],
        'name' => [
          'type' => 'string',
          'description' => 'New name for the term.',
        ],
        'description' => [
          'type' => 'string',
          'description' => 'New description for the term.',
        ],
        'parent' => [
          'type' => 'integer',
          'description' => 'New parent term ID (0 for root-level).',
          'minimum' => 0,
        ],
      ],
      'required' => ['tid'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $tid = $this->getInt($input, 'tid');

      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
      $term = $termStorage->load($tid);

      if (!$term instanceof TermInterface) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Term with ID '%d' does not exist.", $tid),
        );
      }

      $name = $this->getOptionalString($input, 'name');
      if ($name !== NULL) {
        $term->setName($name);
      }

      $description = $this->getOptionalString($input, 'description');
      if ($description !== NULL) {
        $term->setDescription($description);
      }

      $parent = $this->getOptionalInt($input, 'parent');
      if ($parent !== NULL) {
        $term->set('parent', [$parent]);
      }

      $term->save();

      $this->logger->info(
        'MCP: Updated taxonomy term @tid.',
        ['@tid' => $tid],
      );

      return McpResponse::success(NULL, [
        'tid' => $term->id(),
        'name' => (string) $term->label(),
        'vocabulary' => $term->bundle(),
        'description' => (string) $term->getDescription(),
      ]);
    }
    catch (EntityStorageException $e) {
      Error::logException($this->logger, $e);
      return McpResponse::error(
        NULL,
        McpError::INTERNAL_ERROR,
        'Failed to save taxonomy term.',
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
   * Extracts a required integer value from the input array.
   *
   * @param array<string, mixed> $input
   *   The input array.
   * @param string $key
   *   The key to look up.
   *
   * @return int
   *   The integer value.
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

  /**
   * Extracts an optional integer value from the input array.
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

}
