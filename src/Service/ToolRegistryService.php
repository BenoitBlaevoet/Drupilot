<?php

declare(strict_types=1);

namespace Drupal\drupilot\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Owns the enabled/disabled state of every MCP tool.
 *
 * State is persisted under drupilot.settings:enabled_tools.
 */
final class ToolRegistryService {

  private const string CONFIG_NAME = 'drupilot.settings';
  private const string CONFIG_KEY = 'enabled_tools';

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal configuration factory.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns TRUE if the given tool is enabled in configuration.
   */
  public function isEnabled(string $toolId): bool {
    return in_array($toolId, $this->getEnabledToolIds(), TRUE);
  }

  /**
   * Returns the list of enabled tool ids.
   *
   * @return array<int, string>
   *   Numerically indexed list of tool ids.
   */
  public function getEnabledToolIds(): array {
    $raw = $this->configFactory->get(self::CONFIG_NAME)->get(self::CONFIG_KEY);
    if (!is_array($raw)) {
      return [];
    }
    $ids = [];
    foreach ($raw as $value) {
      if (is_string($value) && $value !== '') {
        $ids[] = $value;
      }
    }
    return array_values(array_unique($ids));
  }

  /**
   * Enables a tool.
   */
  public function enableTool(string $toolId): void {
    $ids = $this->getEnabledToolIds();
    if (in_array($toolId, $ids, TRUE)) {
      return;
    }
    $ids[] = $toolId;
    $this->saveIds($ids);
  }

  /**
   * Disables a tool.
   */
  public function disableTool(string $toolId): void {
    $ids = $this->getEnabledToolIds();
    $filtered = array_values(array_filter($ids, static fn (string $id): bool => $id !== $toolId));
    if ($filtered === $ids) {
      return;
    }
    $this->saveIds($filtered);
  }

  /**
   * Persists the canonical list of enabled ids.
   *
   * @param array<int, string> $ids
   *   The full list of enabled tool ids.
   */
  private function saveIds(array $ids): void {
    $this->configFactory
      ->getEditable(self::CONFIG_NAME)
      ->set(self::CONFIG_KEY, array_values(array_unique($ids)))
      ->save();
  }

}
