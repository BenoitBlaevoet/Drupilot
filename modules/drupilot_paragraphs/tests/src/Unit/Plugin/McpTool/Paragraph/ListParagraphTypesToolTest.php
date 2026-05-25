<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot_paragraphs\Unit\Plugin\McpTool\Paragraph;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot_paragraphs\Plugin\McpTool\Paragraph\ListParagraphTypesTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ListParagraphTypesTool.
 */
#[CoversClass(ListParagraphTypesTool::class)]
#[Group('drupilot')]
final class ListParagraphTypesToolTest extends UnitTestCase {

  /**
   * Builds a ListParagraphTypesTool with the given paragraphs_type storage.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The paragraphs_type entity storage mock.
   *
   * @return \Drupal\drupilot_paragraphs\Plugin\McpTool\Paragraph\ListParagraphTypesTool
   *   The tool under test.
   */
  private function buildTool(EntityStorageInterface $storage): ListParagraphTypesTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('paragraphs_type')
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new ListParagraphTypesTool($entityTypeManager, $logger);
  }

  /**
   * Tests that an empty list returns count 0.
   */
  public function testExecuteReturnsEmptyListWhenNoTypesExist(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->with()->willReturn([]);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame(0, $array['result']['count']);
    $this->assertSame([], $array['result']['paragraph_types']);
  }

  /**
   * Tests that a non-empty list returns correct machine_name and label values.
   */
  public function testExecuteReturnsCorrectShapeForMultipleTypes(): void {
    $typeA = $this->createMock(ConfigEntityInterface::class);
    $typeA->method('label')->willReturn('Type A');

    $typeB = $this->createMock(ConfigEntityInterface::class);
    $typeB->method('label')->willReturn('Type B');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->with()->willReturn([
      'type_a' => $typeA,
      'type_b' => $typeB,
    ]);

    $tool = $this->buildTool($storage);

    $response = $tool->execute([]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame(2, $array['result']['count']);

    $items = $array['result']['paragraph_types'];
    $this->assertCount(2, $items);

    $this->assertSame('type_a', $items[0]['machine_name']);
    $this->assertSame('Type A', $items[0]['label']);
    $this->assertSame('type_b', $items[1]['machine_name']);
    $this->assertSame('Type B', $items[1]['label']);
  }

  /**
   * Tests that a Throwable from storage returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnThrowable(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willThrowException(new \RuntimeException('Storage failure'));

    $tool = $this->buildTool($storage);

    $response = $tool->execute([]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
