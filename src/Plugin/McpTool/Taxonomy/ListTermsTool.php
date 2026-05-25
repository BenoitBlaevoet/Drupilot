<?php

declare(strict_types=1);

namespace Drupal\drupilot\Plugin\McpTool\Taxonomy;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupilot\Attribute\McpTool;
use Drupal\drupilot\Plugin\McpTool\McpToolInterface;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot\ValueObject\McpResponse;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists taxonomy terms in a vocabulary.
 */
#[McpTool(
  id: 'term_list',
  label: 'List terms',
  description: 'Returns a list of taxonomy terms in a given vocabulary.',
  category: 'taxonomy',
)]
final class ListTermsTool implements McpToolInterface {

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
          'description' => 'Machine name of the vocabulary to list terms from.',
        ],
        'parent' => [
          'type' => 'integer',
          'description' => 'Parent term ID to list direct children of (0 for root-level terms).',
          'default' => 0,
          'minimum' => 0,
        ],
      ],
      'required' => ['vocabulary'],
      'additionalProperties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input): McpResponse {
    try {
      $vocabulary = $this->getString($input, 'vocabulary');

      $vocabStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      $existingVocab = $vocabStorage->load($vocabulary);

      if (!$existingVocab instanceof VocabularyInterface) {
        return McpResponse::error(
          NULL,
          McpError::INVALID_PARAMS,
          sprintf("Vocabulary '%s' does not exist.", $vocabulary),
        );
      }

      $parent = $this->getOptionalInt($input, 'parent') ?? 0;

      /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

      // loadTree() returns stdClass objects (not entities) with properties:
      // tid, name, description__value, description__format, weight,
      // parents, depth.
      $tree = $termStorage->loadTree($vocabulary, $parent);

      $result = [];
      foreach ($tree as $termData) {
        // stdClass properties — access via property syntax; all values are
        // strings/arrays as returned by the database query.
        $tid = (int) ($termData->tid ?? 0);
        $termName = (string) ($termData->name ?? '');
        $description = (string) ($termData->description__value ?? '');
        $weight = (int) ($termData->weight ?? 0);
        $depth = (int) ($termData->depth ?? 0);
        /** @var array<int|string, mixed> $rawParents */
        $rawParents = (array) ($termData->parents ?? []);
        $parents = array_map('intval', $rawParents);

        $result[] = [
          'tid' => $tid,
          'name' => $termName,
          'description' => $description,
          'weight' => $weight,
          'depth' => $depth,
          'parents' => $parents,
        ];
      }

      return McpResponse::success(NULL, [
        'vocabulary' => $vocabulary,
        'terms' => $result,
        'count' => count($result),
        'items' => $result,
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
