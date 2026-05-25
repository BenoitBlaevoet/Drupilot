<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_layout_paragraphs\Unit\Plugin\McpTool\Paragraph;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Paragraph\ConfigureParagraphTypeLayoutTool;
use Drupal\paragraphs\ParagraphsTypeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ConfigureParagraphTypeLayoutTool.
 */
#[CoversClass(ConfigureParagraphTypeLayoutTool::class)]
#[Group('drupal_mcp')]
final class ConfigureParagraphTypeLayoutToolTest extends UnitTestCase {

  /**
   * A valid LayoutDefinition for layout_onecol used across tests.
   */
  private LayoutDefinition $oneColDefinition;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->oneColDefinition = new LayoutDefinition([
      'id' => 'layout_onecol',
      'label' => 'One column',
      'class' => 'Drupal\Core\Layout\LayoutDefault',
      'regions' => ['content' => ['label' => 'Content']],
    ]);
  }

  /**
   * Builds the tool with given storage and layout definitions.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The paragraphs_type entity storage.
   * @param array<string, mixed> $layoutDefinitions
   *   Layout definitions keyed by layout ID.
   *
   * @return \Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Paragraph\ConfigureParagraphTypeLayoutTool
   *   The tool under test.
   */
  private function buildTool(EntityStorageInterface $storage, array $layoutDefinitions = []): ConfigureParagraphTypeLayoutTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('paragraphs_type')
      ->willReturn($storage);

    $layoutManager = $this->createMock(LayoutPluginManagerInterface::class);
    $layoutManager->method('getDefinitions')->willReturn($layoutDefinitions);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new ConfigureParagraphTypeLayoutTool($entityTypeManager, $layoutManager, $logger);
  }

  /**
   * Tests that a missing paragraph type returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsWhenTypeNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('missing')->willReturn(NULL);

    $tool = $this->buildTool($storage);
    $array = $tool->execute([
      'machine_name' => 'missing',
      'available_layouts' => ['layout_onecol'],
    ])->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that a non-ParagraphsTypeInterface entity returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsWhenEntityIsNotParagraphsType(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(new \stdClass());

    $tool = $this->buildTool($storage);
    $array = $tool->execute([
      'machine_name' => 'bad_type',
      'available_layouts' => ['layout_onecol'],
    ])->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
  }

  /**
   * Tests that unknown layout IDs return INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsForUnknownLayoutIds(): void {
    $paragraphType = $this->createMock(ParagraphsTypeInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage, [
      'layout_onecol' => $this->oneColDefinition,
    ]);

    $array = $tool->execute([
      'machine_name' => 'my_type',
      'available_layouts' => ['layout_onecol', 'nonexistent_layout'],
    ])->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertStringContainsString('nonexistent_layout', $array['error']['message']);
  }

  /**
   * Tests happy path: behavior_plugins is merged and save() is called.
   */
  public function testExecuteMergesBehaviorPluginsAndSaves(): void {
    $paragraphType = $this->createMock(ParagraphsTypeInterface::class);
    $paragraphType->method('get')->with('behavior_plugins')->willReturn([
      'other_behavior' => ['enabled' => TRUE],
    ]);

    $expectedPlugins = [
      'other_behavior' => ['enabled' => TRUE],
      'layout_paragraphs' => [
        'enabled' => TRUE,
        'available_layouts' => ['layout_onecol'],
      ],
    ];

    $paragraphType->expects($this->once())->method('set')->with('behavior_plugins', $expectedPlugins);
    $paragraphType->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage, [
      'layout_onecol' => $this->oneColDefinition,
    ]);

    $array = $tool->execute([
      'machine_name' => 'my_type',
      'available_layouts' => ['layout_onecol'],
    ])->toArray();

    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame('my_type', $array['result']['machine_name']);
    $this->assertTrue($array['result']['enabled']);
    $this->assertSame(['layout_onecol'], $array['result']['available_layouts']);
  }

  /**
   * Tests that enabled=false is respected.
   */
  public function testExecuteRespectsEnabledFalse(): void {
    $paragraphType = $this->createMock(ParagraphsTypeInterface::class);
    $paragraphType->method('get')->with('behavior_plugins')->willReturn([]);
    $paragraphType->expects($this->once())->method('set')->with('behavior_plugins', [
      'layout_paragraphs' => [
        'enabled' => FALSE,
        'available_layouts' => ['layout_onecol'],
      ],
    ]);
    $paragraphType->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($paragraphType);

    $tool = $this->buildTool($storage, [
      'layout_onecol' => $this->oneColDefinition,
    ]);

    $array = $tool->execute([
      'machine_name' => 'my_type',
      'available_layouts' => ['layout_onecol'],
      'enabled' => FALSE,
    ])->toArray();

    $this->assertArrayNotHasKey('error', $array);
    $this->assertFalse($array['result']['enabled']);
  }

  /**
   * Tests that an EntityStorageException from save() returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnEntityStorageException(): void {
    $paragraphType = $this->createMock(ParagraphsTypeInterface::class);
    $paragraphType->method('get')->with('behavior_plugins')->willReturn([]);
    $paragraphType->method('save')->willThrowException(new EntityStorageException('DB error'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($paragraphType);

    $tool = $this->buildTool($storage, [
      'layout_onecol' => $this->oneColDefinition,
    ]);

    $array = $tool->execute([
      'machine_name' => 'my_type',
      'available_layouts' => ['layout_onecol'],
    ])->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that an unexpected Throwable from save() returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnUnexpectedThrowable(): void {
    $paragraphType = $this->createMock(ParagraphsTypeInterface::class);
    $paragraphType->method('get')->with('behavior_plugins')->willReturn([]);
    $paragraphType->method('save')->willThrowException(new \RuntimeException('Unexpected'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($paragraphType);

    $tool = $this->buildTool($storage, [
      'layout_onecol' => $this->oneColDefinition,
    ]);

    $array = $tool->execute([
      'machine_name' => 'my_type',
      'available_layouts' => ['layout_onecol'],
    ])->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
