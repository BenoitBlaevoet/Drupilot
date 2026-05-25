<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp\Unit\Service;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\Service\ToolRegistryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ToolRegistryService enabled/disabled state management.
 */
#[CoversClass(ToolRegistryService::class)]
#[Group('drupal_mcp')]
final class ToolRegistryServiceTest extends UnitTestCase {

  /**
   * Builds a ToolRegistryService wired to a read-only config mock.
   *
   * @param array<int, string> $enabledTools
   *   The list of enabled tool IDs to return from config.
   */
  private function buildRegistry(array $enabledTools): ToolRegistryService {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('enabled_tools')
      ->willReturn($enabledTools);

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('drupal_mcp.settings')
      ->willReturn($config);

    return new ToolRegistryService($factory);
  }

  /**
   * Tests that isEnabled() returns true for a tool that is in the enabled list.
   */
  public function testIsEnabledReturnsTrueForEnabledTool(): void {
    $registry = $this->buildRegistry(['node_create', 'content_type_create']);

    $this->assertTrue($registry->isEnabled('node_create'));
  }

  /**
   * Tests that isEnabled() returns false for a tool not in the enabled list.
   */
  public function testIsEnabledReturnsFalseForDisabledTool(): void {
    $registry = $this->buildRegistry(['node_create']);

    $this->assertFalse($registry->isEnabled('content_type_create'));
  }

  /**
   * Tests that isEnabled() returns false when the enabled tools list is empty.
   */
  public function testIsEnabledReturnsFalseWhenEnabledToolsIsEmpty(): void {
    $registry = $this->buildRegistry([]);

    $this->assertFalse($registry->isEnabled('node_create'));
  }

  /**
   * Tests that getEnabledToolIds() returns exactly the configured list.
   */
  public function testGetEnabledToolIdsReturnsExactConfiguredList(): void {
    $ids = ['node_create', 'content_type_create', 'role_list'];
    $registry = $this->buildRegistry($ids);

    $this->assertSame($ids, $registry->getEnabledToolIds());
  }

  /**
   * Tests that getEnabledToolIds() returns an empty array when no tools are enabled.
   */
  public function testGetEnabledToolIdsReturnsEmptyArrayWhenNoneEnabled(): void {
    $registry = $this->buildRegistry([]);

    $this->assertSame([], $registry->getEnabledToolIds());
  }

  /**
   * Tests that getEnabledToolIds() returns an empty array when config returns null.
   */
  public function testGetEnabledToolIdsReturnsEmptyArrayWhenConfigReturnsNull(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('enabled_tools')
      ->willReturn(NULL);

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('drupal_mcp.settings')
      ->willReturn($config);

    $registry = new ToolRegistryService($factory);

    $this->assertSame([], $registry->getEnabledToolIds());
  }

  /**
   * Tests that enableTool() appends the id to config and saves.
   */
  public function testEnableToolAppendsIdToConfigAndSaves(): void {
    $immutable = $this->createMock(ImmutableConfig::class);
    $immutable->method('get')
      ->with('enabled_tools')
      ->willReturn(['node_create']);

    // The editable config mock: set() and save() must each be called once.
    $editable = $this->createMock(Config::class);
    $editable->expects($this->once())
      ->method('set')
      ->with('enabled_tools', $this->callback(static function (mixed $ids): bool {
        return is_array($ids)
          && in_array('node_create', $ids, TRUE)
          && in_array('content_type_create', $ids, TRUE);
      }))
      ->willReturnSelf();
    $editable->expects($this->once())
      ->method('save');

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('drupal_mcp.settings')
      ->willReturn($immutable);
    $factory->method('getEditable')
      ->with('drupal_mcp.settings')
      ->willReturn($editable);

    $registry = new ToolRegistryService($factory);
    $registry->enableTool('content_type_create');
  }

  /**
   * Tests that enableTool() is idempotent when the tool is already enabled.
   */
  public function testEnableToolIsIdempotentWhenToolAlreadyEnabled(): void {
    $immutable = $this->createMock(ImmutableConfig::class);
    $immutable->method('get')
      ->with('enabled_tools')
      ->willReturn(['node_create']);

    $editable = $this->createMock(Config::class);
    // save() must NOT be called because the tool is already enabled.
    $editable->expects($this->never())->method('save');

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('drupal_mcp.settings')
      ->willReturn($immutable);
    $factory->method('getEditable')
      ->with('drupal_mcp.settings')
      ->willReturn($editable);

    $registry = new ToolRegistryService($factory);
    $registry->enableTool('node_create');
  }

  /**
   * Tests that disableTool() removes the id from config and saves.
   */
  public function testDisableToolRemovesIdFromConfigAndSaves(): void {
    $immutable = $this->createMock(ImmutableConfig::class);
    $immutable->method('get')
      ->with('enabled_tools')
      ->willReturn(['node_create', 'content_type_create']);

    $editable = $this->createMock(Config::class);
    $editable->expects($this->once())
      ->method('set')
      ->with('enabled_tools', $this->callback(static function (mixed $ids): bool {
        return is_array($ids)
          && !in_array('content_type_create', $ids, TRUE)
          && in_array('node_create', $ids, TRUE);
      }))
      ->willReturnSelf();
    $editable->expects($this->once())
      ->method('save');

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('drupal_mcp.settings')
      ->willReturn($immutable);
    $factory->method('getEditable')
      ->with('drupal_mcp.settings')
      ->willReturn($editable);

    $registry = new ToolRegistryService($factory);
    $registry->disableTool('content_type_create');
  }

  /**
   * Tests that disableTool() is idempotent when the tool is already disabled.
   */
  public function testDisableToolIsIdempotentWhenToolAlreadyDisabled(): void {
    $immutable = $this->createMock(ImmutableConfig::class);
    $immutable->method('get')
      ->with('enabled_tools')
      ->willReturn(['node_create']);

    $editable = $this->createMock(Config::class);
    // save() must NOT be called because the tool was not in the list.
    $editable->expects($this->never())->method('save');

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('drupal_mcp.settings')
      ->willReturn($immutable);
    $factory->method('getEditable')
      ->with('drupal_mcp.settings')
      ->willReturn($editable);

    $registry = new ToolRegistryService($factory);
    $registry->disableTool('content_type_create');
  }

}
