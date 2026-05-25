<?php

declare(strict_types=1);

namespace Drupal\drupilot\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when the tool registry is collected for discovery.
 *
 * Subscribers may add, remove, or alter the entity-type context exposed to
 * tools. Carries the entity-type map that triggered discovery.
 */
final class McpToolDiscoverEvent extends Event {

  public const string EVENT_NAME = 'drupilot.tool_discover';

  /**
   * Constructs the event.
   *
   * @param array<string, mixed> $entityTypes
   *   Map of entity-type id => entity-type object.
   */
  public function __construct(
    private array $entityTypes,
  ) {}

  /**
   * Returns the entity-type map.
   *
   * @return array<string, mixed>
   *   Map of entity-type id => entity-type object.
   */
  public function getEntityTypes(): array {
    return $this->entityTypes;
  }

  /**
   * Replaces the entity-type map.
   *
   * @param array<string, mixed> $entityTypes
   *   Map of entity-type id => entity-type object.
   */
  public function setEntityTypes(array $entityTypes): void {
    $this->entityTypes = $entityTypes;
  }

}
