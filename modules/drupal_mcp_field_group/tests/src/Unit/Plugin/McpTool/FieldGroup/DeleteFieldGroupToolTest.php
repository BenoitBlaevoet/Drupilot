<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_field_group\Unit\Plugin\McpTool\FieldGroup;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp_field_group\Plugin\McpTool\FieldGroup\DeleteFieldGroupTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DeleteFieldGroupTool.
 */
#[CoversClass(DeleteFieldGroupTool::class)]
#[Group('drupal_mcp')]
final class DeleteFieldGroupToolTest extends UnitTestCase {

  /**
   * Builds a DeleteFieldGroupTool with the given storage mock.
   *
   * @param string $storageType
   *   The entity storage type key (entity_form_display or entity_view_display).
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage mock.
   *
   * @return \Drupal\drupal_mcp_field_group\Plugin\McpTool\FieldGroup\DeleteFieldGroupTool
   *   The tool under test.
   */
  private function buildTool(string $storageType, EntityStorageInterface $storage): DeleteFieldGroupTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with($storageType)
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new DeleteFieldGroupTool($entityTypeManager, $logger);
  }

  /**
   * Tests that a null display returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsWhenDisplayNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);

    $tool = $this->buildTool('entity_form_display', $storage);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that a missing group in third-party settings returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsWhenGroupNotFound(): void {
    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('getThirdPartySettings')->with('field_group')->willReturn([
      'group_other' => ['label' => 'Other'],
    ]);
    $display->expects($this->never())->method('unsetThirdPartySetting');
    $display->expects($this->never())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool('entity_form_display', $storage);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
  }

  /**
   * Tests the happy path: unsetThirdPartySetting and save are called.
   */
  public function testExecuteUnsetsGroupAndSavesDisplay(): void {
    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('getThirdPartySettings')->with('field_group')->willReturn([
      'group_meta' => ['label' => 'Meta'],
    ]);
    $display->expects($this->once())
      ->method('unsetThirdPartySetting')
      ->with('field_group', 'group_meta');
    $display->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool('entity_form_display', $storage);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame('group_meta', $array['result']['group_name']);
    $this->assertTrue($array['result']['deleted']);
  }

  /**
   * Tests that an EntityStorageException on save returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnEntityStorageException(): void {
    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('getThirdPartySettings')->with('field_group')->willReturn([
      'group_meta' => ['label' => 'Meta'],
    ]);
    $display->method('unsetThirdPartySetting');
    $display->method('save')->willThrowException(new EntityStorageException('DB error'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool('entity_form_display', $storage);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
  }

  /**
   * Tests that an unexpected Throwable (non-EntityStorageException) returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnUnexpectedThrowable(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willThrowException(new \RuntimeException('Unexpected failure'));

    $tool = $this->buildTool('entity_form_display', $storage);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
