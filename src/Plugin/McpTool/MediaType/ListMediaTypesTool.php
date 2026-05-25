<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Plugin\McpTool\MediaType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists all media types with their source plugin and media count.
 */
#[McpTool(
  id: 'media_type_list',
  label: 'List media types',
  description: 'Lists all media types with their source plugin, source field, and media count.',
  category: 'media_type',
)]
final class ListMediaTypesTool implements McpToolInterface {

  /**
   * Core media bundle machine names, in canonical order.
   *
   * @var list<string>
   */
  private const CORE_BUNDLES = ['image', 'document', 'video', 'remote_video', 'audio'];

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
   *   Plugin configuration array.
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
      $storage = $this->entityTypeManager->getStorage('media_type');
      $mediaStorage = $this->entityTypeManager->getStorage('media');

      /** @var array<string, \Drupal\media\MediaTypeInterface> $mediaTypes */
      $mediaTypes = $storage->loadMultiple();

      /** @var array<int, array<string, mixed>> $coreItems */
      $coreItems = [];
      /** @var array<string, array<string, mixed>> $customItems */
      $customItems = [];

      foreach ($mediaTypes as $id => $mediaType) {
        $isCore = in_array($id, self::CORE_BUNDLES, TRUE);

        $sourcePlugin = $mediaType->getSource();
        $sourcePluginId = $sourcePlugin->getPluginId();

        $sourceDefinition = $sourcePlugin->getSourceFieldDefinition($mediaType);
        $sourceFieldName = $sourceDefinition !== NULL ? $sourceDefinition->getName() : '';

        $mediaCount = (int) $mediaStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('bundle', $id)
          ->count()
          ->execute();

        $item = [
          'machine_name' => $id,
          'label' => (string) $mediaType->label(),
          'description' => $mediaType->getDescription(),
          'source' => $sourcePluginId,
          'source_field' => $sourceFieldName,
          'is_core' => $isCore,
          'media_count' => $mediaCount,
        ];

        if ($isCore) {
          $coreItems[] = $item;
        }
        else {
          $customItems[(string) $mediaType->label()] = $item;
        }
      }

      // Sort core bundles in canonical order.
      usort($coreItems, function (array $a, array $b): int {
        $posA = array_search($a['machine_name'], self::CORE_BUNDLES, TRUE);
        $posB = array_search($b['machine_name'], self::CORE_BUNDLES, TRUE);
        return ($posA === FALSE ? PHP_INT_MAX : $posA) <=> ($posB === FALSE ? PHP_INT_MAX : $posB);
      });

      // Sort custom bundles alphabetically by label.
      ksort($customItems);

      $result = array_merge($coreItems, array_values($customItems));

      return McpResponse::success(NULL, ['media_types' => $result, 'items' => $result]);
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
