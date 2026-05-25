<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot_field_group\Unit\Plugin\McpTool\FieldGroup;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\CreateFieldGroupTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests CreateFieldGroupTool.
 */
#[CoversClass(CreateFieldGroupTool::class)]
#[Group('drupilot')]
final class CreateFieldGroupToolTest extends UnitTestCase {

  /**
   * Builds a CreateFieldGroupTool wired with the given storage map.
   *
   * @param array<string, \Drupal\Core\Entity\EntityStorageInterface> $storageMap
   *   Map of storage-type string to mock storage.
   *
   * @return \Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\CreateFieldGroupTool
   *   The tool under test.
   */
  private function buildTool(array $storageMap): CreateFieldGroupTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->willReturnCallback(function (string $type) use ($storageMap): EntityStorageInterface {
        if (!isset($storageMap[$type])) {
          throw new \InvalidArgumentException("No storage mock for '$type'");
        }
        return $storageMap[$type];
      });

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new CreateFieldGroupTool($entityTypeManager, $logger);
  }

  /**
   * Creates a display mock with no existing groups and expects one set+save.
   *
   * @param string $groupName
   *   The group that will be created.
   * @param array<string, mixed> $expectedSetting
   *   The expected third-party setting value.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The display mock.
   */
  private function displayWithNoGroups(string $groupName, array $expectedSetting): EntityDisplayInterface {
    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('getThirdPartySettings')->with('field_group')->willReturn([]);
    $display->expects($this->once())
      ->method('setThirdPartySetting')
      ->with('field_group', $groupName, $expectedSetting);
    $display->expects($this->once())->method('save');

    return $display;
  }

  /**
   * Tests that a null display (not found) returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsWhenDisplayNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);

    $tool = $this->buildTool(['entity_form_display' => $storage]);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
      'label' => 'Meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that a duplicate group name returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsWhenGroupAlreadyExists(): void {
    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('getThirdPartySettings')->with('field_group')->willReturn([
      'group_meta' => ['label' => 'Existing'],
    ]);
    $display->expects($this->never())->method('setThirdPartySetting');
    $display->expects($this->never())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool(['entity_form_display' => $storage]);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
      'label' => 'Meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
  }

  /**
   * Tests that display_mode 'form' loads from entity_form_display storage.
   */
  public function testExecuteLoadsFormDisplayWhenDisplayModeIsForm(): void {
    $formStorage = $this->createMock(EntityStorageInterface::class);
    $formStorage->expects($this->once())->method('load')
      ->with('node.article.default')
      ->willReturn(NULL);

    $tool = $this->buildTool(['entity_form_display' => $formStorage]);

    $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
      'label' => 'Meta',
    ]);
    // No assertion needed — PHPUnit will verify the expects($this->once()) above.
  }

  /**
   * Tests that display_mode 'view' loads from entity_view_display storage.
   */
  public function testExecuteLoadsViewDisplayWhenDisplayModeIsView(): void {
    $viewStorage = $this->createMock(EntityStorageInterface::class);
    $viewStorage->expects($this->once())->method('load')
      ->with('node.article.default')
      ->willReturn(NULL);

    $tool = $this->buildTool(['entity_view_display' => $viewStorage]);

    $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'view',
      'group_name' => 'group_meta',
      'label' => 'Meta',
    ]);
  }

  /**
   * Tests the happy path with weight and children provided.
   */
  public function testExecuteCreatesGroupWithWeightAndChildren(): void {
    $expectedSetting = [
      'label' => 'Meta',
      'children' => ['field_title', 'field_body'],
      'parent_name' => '',
      'weight' => 5,
      'format_type' => 'details',
      'format_settings' => [],
      'region' => 'content',
    ];

    $display = $this->displayWithNoGroups('group_meta', $expectedSetting);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool(['entity_form_display' => $storage]);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
      'label' => 'Meta',
      'weight' => 5,
      'children' => ['field_title', 'field_body'],
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame('group_meta', $array['result']['group_name']);
    $this->assertSame('Meta', $array['result']['label']);
    $this->assertSame('node', $array['result']['entity_type']);
    $this->assertSame('article', $array['result']['bundle']);
  }

  /**
   * Tests the happy path with minimal options (no weight, no children).
   */
  public function testExecuteCreatesGroupWithMinimalOptions(): void {
    $expectedSetting = [
      'label' => 'Meta',
      'children' => [],
      'parent_name' => '',
      'weight' => 0,
      'format_type' => 'details',
      'format_settings' => [],
      'region' => 'content',
    ];

    $display = $this->displayWithNoGroups('group_meta', $expectedSetting);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool(['entity_form_display' => $storage]);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
      'label' => 'Meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame('group_meta', $array['result']['group_name']);
  }

  /**
   * Tests that an EntityStorageException on save returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnEntityStorageException(): void {
    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('getThirdPartySettings')->with('field_group')->willReturn([]);
    $display->method('setThirdPartySetting');
    $display->method('save')->willThrowException(new EntityStorageException('DB error'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($display);

    $tool = $this->buildTool(['entity_form_display' => $storage]);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
      'label' => 'Meta',
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

    $tool = $this->buildTool(['entity_form_display' => $storage]);

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle' => 'article',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
      'label' => 'Meta',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
