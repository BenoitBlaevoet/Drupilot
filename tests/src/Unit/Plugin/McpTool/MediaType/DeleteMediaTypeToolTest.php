<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot\Unit\Plugin\McpTool\MediaType;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupilot\Plugin\McpTool\MediaType\DeleteMediaTypeTool;
use Drupal\drupilot\ValueObject\McpError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the core-bundle protection guard in DeleteMediaTypeTool.
 */
#[CoversClass(DeleteMediaTypeTool::class)]
#[Group('drupilot')]
final class DeleteMediaTypeToolTest extends UnitTestCase {

  /**
   * Builds a DeleteMediaTypeTool with the given media_type storage mock.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $mediaTypeStorage
   *   The media_type entity storage mock.
   *
   * @return \Drupal\drupilot\Plugin\McpTool\MediaType\DeleteMediaTypeTool
   *   The tool under test.
   */
  private function buildTool(EntityStorageInterface $mediaTypeStorage): DeleteMediaTypeTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('media_type')
      ->willReturn($mediaTypeStorage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    // DeleteMediaTypeTool constructor: (EntityTypeManagerInterface, LoggerChannelInterface)
    return new DeleteMediaTypeTool($entityTypeManager, $logger);
  }

  /**
   * Returns a media_type storage mock that must never have load() called.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The storage mock.
   */
  private function unusedStorage(): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->never())->method('load');
    return $storage;
  }

  /**
   * Provides all core media bundle machine names that must be protected.
   *
   * @return array<string, array{string}>
   *   Keyed by description.
   */
  public static function coreBundleProvider(): array {
    return [
      'image bundle'        => ['image'],
      'video bundle'        => ['video'],
      'document bundle'     => ['document'],
      'audio bundle'        => ['audio'],
      'remote_video bundle' => ['remote_video'],
    ];
  }

  /**
   * Tests that core media bundles cannot be deleted.
   *
   * @param string $machineName
   *   The core bundle machine name.
   */
  #[DataProvider('coreBundleProvider')]
  public function testExecuteRejectsCoreBundleDeletion(string $machineName): void {
    $tool = $this->buildTool($this->unusedStorage());

    $response = $tool->execute(['machine_name' => $machineName]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array, "Expected error when deleting core bundle '$machineName'.");
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that a non-existent custom media type returns an error.
   */
  public function testExecuteReturnsErrorWhenCustomMediaTypeNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('custom_media')->willReturn(NULL);

    $tool = $this->buildTool($storage);

    $response = $tool->execute(['machine_name' => 'custom_media']);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
  }

  /**
   * Tests that an existing custom media type with no media entities is deleted.
   *
   * This test wires the 'media' storage in addition to 'media_type' so that
   * the entity query path does not error before reaching delete().
   */
  public function testExecuteDeletesExistingCustomMediaTypeWithNoEntities(): void {
    $mediaType = $this->createMock(EntityInterface::class);
    $mediaType->expects($this->once())->method('delete');

    $mediaTypeStorage = $this->createMock(EntityStorageInterface::class);
    $mediaTypeStorage->method('load')->with('custom_media')->willReturn($mediaType);

    // The 'media' storage query returns zero ids (no entities to delete).
    $query = $this->getMockBuilder(QueryInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $mediaStorage = $this->createMock(EntityStorageInterface::class);
    $mediaStorage->method('getQuery')->willReturn($query);
    $mediaStorage->expects($this->never())->method('delete');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['media_type', $mediaTypeStorage],
        ['media', $mediaStorage],
      ]);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->method('info');

    $tool = new DeleteMediaTypeTool($entityTypeManager, $logger);

    $response = $tool->execute(['machine_name' => 'custom_media']);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame('custom_media', $array['result']['machine_name']);
    $this->assertSame(0, $array['result']['deleted_media_count']);
  }

}
