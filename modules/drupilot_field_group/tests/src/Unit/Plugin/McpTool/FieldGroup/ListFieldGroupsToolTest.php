<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot_field_group\Unit\Plugin\McpTool\FieldGroup;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\ListFieldGroupsTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ListFieldGroupsTool.
 */
#[CoversClass(ListFieldGroupsTool::class)]
#[Group('drupilot')]
final class ListFieldGroupsToolTest extends UnitTestCase {

  /**
   * Builds a ListFieldGroupsTool with the given storage mock.
   *
   * @param string $storageType
   *   The entity storage type key (entity_form_display or entity_view_display).
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage mock.
   *
   * @return \Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\ListFieldGroupsTool
   *   The tool under test.
   */
  private function buildTool(string $storageType, EntityStorageInterface $storage): ListFieldGroupsTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with($storageType)
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new ListFieldGroupsTool($entityTypeManager, $logger);
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
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that an empty groups array returns count 0.
   */
  public function testExecuteReturnsEmptyGroupsWhenNoneExist(): void {
    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('getThirdPartySettings')->with('field_group')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool('entity_form_display', $storage);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame(0, $array['result']['count']);
    $this->assertSame([], $array['result']['groups']);
  }

  /**
   * Tests that groups are returned with the correct keys.
   */
  public function testExecuteReturnsCorrectShapeForGroups(): void {
    $groups = [
      'group_meta' => [
        'label' => 'Meta',
        'format_type' => 'details',
        'children' => ['field_title'],
        'weight' => 3,
      ],
      'group_body' => [
        'label' => 'Body',
        'format_type' => 'fieldset',
        'children' => ['field_body', 'field_summary'],
        'weight' => 10,
      ],
    ];

    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('getThirdPartySettings')->with('field_group')->willReturn($groups);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool('entity_form_display', $storage);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame(2, $array['result']['count']);

    $items = $array['result']['groups'];
    $this->assertCount(2, $items);

    $this->assertSame('group_meta', $items[0]['group_name']);
    $this->assertSame('Meta', $items[0]['label']);
    $this->assertSame('details', $items[0]['format_type']);
    $this->assertSame(['field_title'], $items[0]['children']);
    $this->assertSame(3, $items[0]['weight']);

    $this->assertSame('group_body', $items[1]['group_name']);
    $this->assertSame('Body', $items[1]['label']);
    $this->assertSame('fieldset', $items[1]['format_type']);
    $this->assertSame(['field_body', 'field_summary'], $items[1]['children']);
    $this->assertSame(10, $items[1]['weight']);
  }

  /**
   * Tests that a Throwable from load returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnThrowable(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willThrowException(new \RuntimeException('Storage failure'));

    $tool = $this->buildTool('entity_form_display', $storage);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
