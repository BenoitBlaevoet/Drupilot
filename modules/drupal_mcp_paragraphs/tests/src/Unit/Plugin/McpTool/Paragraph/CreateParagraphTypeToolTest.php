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
use Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\CreateParagraphTypeTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests CreateParagraphTypeTool.
 */
#[CoversClass(CreateParagraphTypeTool::class)]
#[Group('drupal_mcp')]
final class CreateParagraphTypeToolTest extends UnitTestCase {

  /**
   * Builds a CreateParagraphTypeTool with the given paragraphs_type storage.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The paragraphs_type entity storage mock.
   *
   * @return \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\CreateParagraphTypeTool
   *   The tool under test.
   */
  private function buildTool(EntityStorageInterface $storage): CreateParagraphTypeTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('paragraphs_type')
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new CreateParagraphTypeTool($entityTypeManager, $logger);
  }

  /**
   * Tests that creating a duplicate paragraph type returns INVALID_PARAMS.
   */
  public function testExecuteReturnsDuplicateErrorWhenTypeAlreadyExists(): void {
    $existingEntity = $this->createMock(ConfigEntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($existingEntity);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'My Type',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests the happy path when a description is provided.
   */
  public function testExecuteCreatesParagraphTypeWithDescription(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->expects($this->once())->method('set')->with('description', 'A nice description');
    $paragraphType->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn(NULL);
    $storage->method('create')->with([
      'id' => 'my_type',
      'label' => 'My Type',
    ])->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'My Type',
      'description' => 'A nice description',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame('my_type', $array['result']['machine_name']);
    $this->assertSame('My Type', $array['result']['label']);
  }

  /**
   * Tests the happy path when no description is provided.
   */
  public function testExecuteCreatesParagraphTypeWithoutDescription(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->expects($this->never())->method('set');
    $paragraphType->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn(NULL);
    $storage->method('create')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'My Type',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame('my_type', $array['result']['machine_name']);
  }

  /**
   * Tests that an EntityStorageException on save returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnEntityStorageException(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->method('save')->willThrowException(new EntityStorageException('DB error'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'My Type',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that an unexpected Throwable (non-EntityStorageException) returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnUnexpectedThrowable(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->method('save')->willThrowException(new \RuntimeException('Unexpected'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $storage->method('create')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([
      'machine_name' => 'my_type',
      'label' => 'My Type',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
