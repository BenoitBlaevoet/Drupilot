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
use Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\DeleteParagraphTypeTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DeleteParagraphTypeTool.
 */
#[CoversClass(DeleteParagraphTypeTool::class)]
#[Group('drupal_mcp')]
final class DeleteParagraphTypeToolTest extends UnitTestCase {

  /**
   * Builds a DeleteParagraphTypeTool with the given paragraphs_type storage.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The paragraphs_type entity storage mock.
   *
   * @return \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\DeleteParagraphTypeTool
   *   The tool under test.
   */
  private function buildTool(EntityStorageInterface $storage): DeleteParagraphTypeTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('paragraphs_type')
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new DeleteParagraphTypeTool($entityTypeManager, $logger);
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
   * Tests the happy path: delete() is called and success is returned.
   */
  public function testExecuteCallsDeleteOnExistingType(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->expects($this->once())->method('delete');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute(['machine_name' => 'my_type']);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame('my_type', $array['result']['machine_name']);
    $this->assertTrue($array['result']['deleted']);
  }

  /**
   * Tests that an EntityStorageException returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnEntityStorageException(): void {
    $paragraphType = $this->createMock(ConfigEntityInterface::class);
    $paragraphType->method('delete')->willThrowException(new EntityStorageException('DB error'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute(['machine_name' => 'my_type']);

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
    $paragraphType->method('delete')->willThrowException(new \RuntimeException('Unexpected'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('my_type')->willReturn($paragraphType);

    $tool = $this->buildTool($storage);

    $response = $tool->execute(['machine_name' => 'my_type']);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
