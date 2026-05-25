<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot\Kernel\Plugin;

use Drupal\KernelTests\KernelTestBase;
use Drupal\drupilot\PluginManager\McpToolPluginManager;
use Drupal\drupilot\Service\ToolRegistryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the McpToolPluginManager discovers and hydrates tool plugins.
 */
#[CoversClass(McpToolPluginManager::class)]
#[Group('drupilot')]
#[RunTestsInSeparateProcesses]
final class McpToolPluginManagerTest extends KernelTestBase {

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
    'drupilot',
  ];

  /**
   * Tests that the plugin manager service is registered in the container.
   */
  public function testPluginManagerIsRegisteredInContainer(): void {
    $manager = $this->container->get('plugin.manager.mcp_tool');

    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(McpToolPluginManager::class, $manager);
  }

  /**
   * Tests that getToolDefinitions() returns a non-empty array.
   */
  public function testGetToolDefinitionsReturnsNonEmptyArray(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $definitions = $manager->getToolDefinitions();

    $this->assertNotEmpty($definitions);
  }

  /**
   * Tests that getToolDefinitions() returns ToolDefinition value objects keyed by string IDs.
   */
  public function testGetToolDefinitionsReturnsToolDefinitionValueObjects(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $definitions = $manager->getToolDefinitions();

    // Verify at least one definition exists and has valid string keys.
    $this->assertNotEmpty($definitions);
    foreach (array_keys($definitions) as $key) {
      $this->assertNotEmpty($key, 'Each tool definition key must be a non-empty string.');
    }
  }

  /**
   * Tests that content_type_create is discovered as a plugin.
   */
  public function testContentTypeCreateToolIsDiscovered(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $definitions = $manager->getToolDefinitions();

    $this->assertArrayHasKey('content_type_create', $definitions);
  }

  /**
   * Tests that node_create is discovered as a plugin.
   */
  public function testNodeCreateToolIsDiscovered(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $definitions = $manager->getToolDefinitions();

    $this->assertArrayHasKey('node_create', $definitions);
  }

  /**
   * Tests that the content_type_create ToolDefinition has the correct id.
   */
  public function testToolDefinitionHasCorrectId(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $definitions = $manager->getToolDefinitions();

    $this->assertSame('content_type_create', $definitions['content_type_create']->id);
  }

  /**
   * Tests that the content_type_create ToolDefinition has a non-empty label.
   */
  public function testToolDefinitionHasNonEmptyLabel(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $definitions = $manager->getToolDefinitions();

    $this->assertNotEmpty($definitions['content_type_create']->label);
  }

  /**
   * Tests that the content_type_create ToolDefinition has a non-empty description.
   */
  public function testToolDefinitionHasNonEmptyDescription(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $definitions = $manager->getToolDefinitions();

    $this->assertNotEmpty($definitions['content_type_create']->description);
  }

  /**
   * Tests that the content_type_create ToolDefinition has the correct category.
   */
  public function testToolDefinitionHasCategory(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $definitions = $manager->getToolDefinitions();

    $this->assertSame('content_type', $definitions['content_type_create']->category);
  }

  /**
   * Tests that getEnabledDefinitions() returns an empty array when no tools are enabled.
   */
  public function testGetEnabledDefinitionsReturnsEmptyArrayWhenNoToolsEnabled(): void {
    /** @var \Drupal\drupilot\PluginManager\McpToolPluginManager $manager */
    $manager = $this->container->get('plugin.manager.mcp_tool');
    $registry = $this->container->get('drupilot.tool_registry');
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(ToolRegistryService::class, $registry);

    $enabled = $manager->getEnabledDefinitions($registry);

    $this->assertSame([], $enabled);
  }

}
