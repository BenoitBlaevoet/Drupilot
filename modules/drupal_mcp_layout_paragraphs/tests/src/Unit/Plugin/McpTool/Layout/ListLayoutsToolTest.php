<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_layout_paragraphs\Unit\Plugin\McpTool\Layout;

use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Layout\ListLayoutsTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ListLayoutsTool.
 */
#[CoversClass(ListLayoutsTool::class)]
#[Group('drupal_mcp')]
final class ListLayoutsToolTest extends UnitTestCase {

  /**
   * Builds a ListLayoutsTool with a mocked layout plugin manager.
   *
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layoutPluginManager
   *   The layout plugin manager mock.
   *
   * @return \Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Layout\ListLayoutsTool
   *   The tool under test.
   */
  private function buildTool(LayoutPluginManagerInterface $layoutPluginManager): ListLayoutsTool {
    $logger = $this->createMock(LoggerChannelInterface::class);
    return new ListLayoutsTool($layoutPluginManager, $logger);
  }

  /**
   * Tests that an empty definitions map returns count 0 and empty layouts.
   */
  public function testExecuteReturnsEmptyWhenNoDefinitions(): void {
    $manager = $this->createMock(LayoutPluginManagerInterface::class);
    $manager->method('getDefinitions')->willReturn([]);

    $tool = $this->buildTool($manager);
    $array = $tool->execute([])->toArray();

    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame(0, $array['result']['count']);
    $this->assertSame([], $array['result']['items']);
  }

  /**
   * Tests that non-LayoutDefinition entries are silently skipped.
   */
  public function testExecuteSkipsNonLayoutDefinitionEntries(): void {
    $manager = $this->createMock(LayoutPluginManagerInterface::class);
    $manager->method('getDefinitions')->willReturn([
      'bad_entry' => ['id' => 'bad_entry', 'label' => 'Not a LayoutDefinition'],
    ]);

    $tool = $this->buildTool($manager);
    $array = $tool->execute([])->toArray();

    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame(0, $array['result']['count']);
  }

  /**
   * Tests that include_regions=true (default) populates region data.
   */
  public function testExecuteIncludesRegionsByDefault(): void {
    $definition = new LayoutDefinition([
      'id' => 'layout_twocol',
      'label' => 'Two column',
      'class' => 'Drupal\Core\Layout\LayoutDefault',
      'regions' => [
        'left' => ['label' => 'Left'],
        'right' => ['label' => 'Right'],
      ],
    ]);

    $manager = $this->createMock(LayoutPluginManagerInterface::class);
    $manager->method('getDefinitions')->willReturn([
      'layout_twocol' => $definition,
    ]);

    $tool = $this->buildTool($manager);
    $array = $tool->execute([])->toArray();

    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame(1, $array['result']['count']);

    $layout = $array['result']['items'][0];
    $this->assertSame('layout_twocol', $layout['id']);
    $this->assertSame('Two column', $layout['label']);
    $this->assertArrayHasKey('category', $layout);
    $this->assertSame('', $layout['category']);
    $this->assertArrayHasKey('regions', $layout);
    $this->assertCount(2, $layout['regions']);

    $regionNames = array_column($layout['regions'], 'name');
    $this->assertContains('left', $regionNames);
    $this->assertContains('right', $regionNames);
  }

  /**
   * Tests that include_regions=false omits the regions key.
   */
  public function testExecuteOmitsRegionsWhenNotRequested(): void {
    $definition = new LayoutDefinition([
      'id' => 'layout_onecol',
      'label' => 'One column',
      'class' => 'Drupal\Core\Layout\LayoutDefault',
      'regions' => [
        'content' => ['label' => 'Content'],
      ],
    ]);

    $manager = $this->createMock(LayoutPluginManagerInterface::class);
    $manager->method('getDefinitions')->willReturn([
      'layout_onecol' => $definition,
    ]);

    $tool = $this->buildTool($manager);
    $array = $tool->execute(['include_regions' => FALSE])->toArray();

    $this->assertArrayNotHasKey('error', $array);
    $layout = $array['result']['items'][0];
    $this->assertArrayNotHasKey('regions', $layout);
  }

  /**
   * Tests that a Throwable from getDefinitions() returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnThrowable(): void {
    $manager = $this->createMock(LayoutPluginManagerInterface::class);
    $manager->method('getDefinitions')->willThrowException(new \RuntimeException('Plugin manager failure'));

    $tool = $this->buildTool($manager);
    $array = $tool->execute([])->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
