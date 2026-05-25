<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\Taxonomy;

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
 * Creates a new Drupal taxonomy term.
 */
#[McpTool(
  id: 'term_create',
  label: 'Create term',
  description: 'Creates a new Drupal taxonomy term in the given vocabulary.',
  category: 'taxonomy',
)]
final class CreateTermTool implements McpToolInterface {

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
        'vocabulary' => [
          'type' => 'string',
          'description' => 'Machine name of the vocabulary to create the term in.',
        ],
        'name' => [
          'type' => 'string',
          'description' => 'Name of the term.',
        ],
        'description' => [
          'type' => 'string',
          'description' => 'Optional description for the term.',
        ],
        'parent' => [
          'type' => 'integer',
          'description' => 'Term ID of the parent term (0 for root-level terms).',
          'default' => 0,
          'minimum' => 0,
        ],
      ],
      'required' => ['vocabulary', 'name'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $vocabulary = $this->getString($input, 'vocabulary');
      $name = $this->getString($input, 'name');

      $vocabStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      $existingVocab = $vocabStorage->load($vocabulary);

      if ($existingVocab === NULL) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Vocabulary '%s' does not exist.", $vocabulary),
        );
      }

      $parent = $this->getOptionalInt($input, 'parent') ?? 0;
      $description = $this->getOptionalString($input, 'description');

      $termData = [
        'vid' => $vocabulary,
        'name' => $name,
        'parent' => [$parent],
      ];

      if ($description !== NULL) {
        $termData['description'] = $description;
      }

      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $termStorage->create($termData);
      $term->save();

      $this->logger->info(
        'MCP: Created taxonomy term @name in vocabulary @vocabulary (tid: @tid).',
        [
          '@name' => $name,
          '@vocabulary' => $vocabulary,
          '@tid' => $term->id(),
        ],
      );

      return McpResponse::success(NULL, [
        'tid' => $term->id(),
        'name' => $name,
        'vocabulary' => $vocabulary,
        'description' => $description ?? '',
        'parent' => $parent,
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
