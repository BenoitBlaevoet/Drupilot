<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_paragraphs\Unit\Plugin\McpTool\Paragraph;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\UpdateParagraphTypeTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests UpdateParagraphTypeTool.
 */
#[CoversClass(UpdateParagraphTypeTool::class)]
#[Group('drupal_mcp')]
final class UpdateParagraphTypeToolTest extends UnitTestCase {

  /**
   * Builds an UpdateParagraphTypeTool with the given paragraphs_type storage.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The paragraphs_type entity storage mock.
   *
   * @return \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\UpdateParagraphTypeTool
   *   The tool under test.
   */
  private function buildTool(EntityStorageInterface $storage): UpdateParagraphTypeTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('paragraphs_type')
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new UpdateParagraphTypeTool($entityTypeManager, $logger);
  }

  /**
   * Tests that a not-found paragraph type returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsWhenTypeNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('missing_type')->willReturn(NULL);

    $tool = $this->buildTool($storage);

    $response = $tool->execute(['machine_name' => 'missing_type']);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that updating the label calls set('label') and save().
   */
  public function testExecuteUpdatesLabel(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->expects($this->once())->method('set')->with('label', 'New Label');
    $paragraphType->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'New Label',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame('my_type', $array['result']['machine_name']);
  }

  /**
   * Tests that updating the description calls set('description') and save().
   */
  public function testExecuteUpdatesDescription(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->expects($this->once())->method('set')->with('description', 'New description');
    $paragraphType->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'description' => 'New description',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame('my_type', $array['result']['machine_name']);
  }

  /**
   * Tests that an EntityStorageException on save returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnEntityStorageException(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->method('save')->willThrowException(new EntityStorageException('DB error'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'Updated',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
  }

  /**
   * Tests that an unexpected Throwable (non-EntityStorageException) returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnUnexpectedThrowable(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->method('save')->willThrowException(new \RuntimeException('Unexpected'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'Updated',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that both label and description are updated in a single call.
   */
  public function testExecuteUpdatesBothLabelAndDescription(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->expects($this->exactly(2))->method('set');
    $paragraphType->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'New Label',
      'description' => 'New description',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame('my_type', $array['result']['machine_name']);
  }

}
