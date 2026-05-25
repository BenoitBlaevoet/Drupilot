<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\drupal_mcp\Service\ToolRegistryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ToolRegistryService wiring and config persistence in the container.
 */
#[CoversClass(ToolRegistryService::class)]
#[Group('drupal_mcp')]
#[RunTestsInSeparateProcesses]
final class ToolRegistryServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<int, string>
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'field_ui',
    'text',
    'taxonomy',
    'media',
    'file',
    'image',
    'drupal_mcp',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['drupal_mcp']);
  }

  /**
   * Tests that the service is correctly wired in the container.
   */
  public function testServiceIsWiredInContainer(): void {
    $registry = $this->container->get('drupal_mcp.tool_registry');

    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(ToolRegistryService::class, $registry);
  }

  /**
   * Tests that isEnabled() returns false by default for any tool.
   */
  public function testIsEnabledReturnsFalseByDefaultForAnyTool(): void {
    /** @var \Drupal\drupal_mcp\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupal_mcp.tool_registry');

    $this->assertFalse($registry->isEnabled('content_type_create'));
  }

  /**
   * Tests that getEnabledToolIds() returns an empty array by default.
   */
  public function testGetEnabledToolIdsReturnsEmptyArrayByDefault(): void {
    /** @var \Drupal\drupal_mcp\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupal_mcp.tool_registry');

    $this->assertSame([], $registry->getEnabledToolIds());
  }

  /**
   * Tests that enableTool() persists the tool to config and isEnabled() reflects it.
   */
  public function testEnableToolPersistsToConfig(): void {
    /** @var \Drupal\drupal_mcp\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupal_mcp.tool_registry');

    $registry->enableTool('content_type_create');

    $this->assertTrue($registry->isEnabled('content_type_create'));
  }

  /**
   * Tests that disableTool() removes the tool from config.
   */
  public function testDisableToolRemovesToolFromConfig(): void {
    /** @var \Drupal\drupal_mcp\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupal_mcp.tool_registry');

    $registry->enableTool('content_type_create');
    $registry->disableTool('content_type_create');

    $this->assertFalse($registry->isEnabled('content_type_create'));
  }

  /**
   * Tests that enableTool() is idempotent and does not duplicate entries.
   */
  public function testEnableToolIsIdempotent(): void {
    /** @var \Drupal\drupal_mcp\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupal_mcp.tool_registry');

    $registry->enableTool('content_type_create');
    $registry->enableTool('content_type_create');

    $ids = $registry->getEnabledToolIds();
    $this->assertCount(1, array_filter($ids, static fn (string $id): bool => $id === 'content_type_create'));
  }

}
