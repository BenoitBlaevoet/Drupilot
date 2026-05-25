<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_layout_paragraphs\Unit\Plugin\McpTool\Field;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\ValueObject\McpError;
use Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Field\ConfigureLayoutParagraphsDisplayTool;
use Drupal\field\FieldConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ConfigureLayoutParagraphsDisplayTool.
 */
#[CoversClass(ConfigureLayoutParagraphsDisplayTool::class)]
#[Group('drupal_mcp')]
final class ConfigureLayoutParagraphsDisplayToolTest extends UnitTestCase {

  /**
   * The default input used for the happy-path tests.
   *
   * @var array<string, mixed>
   */
  private array $defaultInput = [
    'entity_type' => 'node',
    'bundle' => 'article',
    'field_name' => 'field_paragraphs',
  ];

  /**
   * Builds the tool with the given mocks.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager mock.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager mock.
   *
   * @return \Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Field\ConfigureLayoutParagraphsDisplayTool
   *   The tool under test.
   */
  private function buildTool(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
  ): ConfigureLayoutParagraphsDisplayTool {
    $logger = $this->createMock(LoggerChannelInterface::class);
    return new ConfigureLayoutParagraphsDisplayTool($entityTypeManager, $entityFieldManager, $logger);
  }

  /**
   * Creates a mock FieldConfigInterface for entity_reference_revisions.
   *
   * @return \Drupal\field\FieldConfigInterface
   *   The mocked field config.
   */
  private function buildErrFieldConfig(): FieldConfigInterface {
    $storageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storageDefinition->method('getType')->willReturn('entity_reference_revisions');

    $fieldConfig = $this->createMock(FieldConfigInterface::class);
    $fieldConfig->method('getFieldStorageDefinition')->willReturn($storageDefinition);

    return $fieldConfig;
  }

  /**
   * Tests that a missing field returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsWhenFieldNotFound(): void {
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn([]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $tool = $this->buildTool($entityTypeManager, $entityFieldManager);
    $array = $tool->execute($this->defaultInput)->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that a base field (not FieldConfigInterface) returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsForBaseField(): void {
    $baseField = $this->createMock(BaseFieldDefinition::class);

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willReturn([
      'field_paragraphs' => $baseField,
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $tool = $this->buildTool($entityTypeManager, $entityFieldManager);
    $array = $tool->execute($this->defaultInput)->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
  }

  /**
   * Tests that a non-entity_reference_revisions field returns INVALID_PARAMS.
   */
  public function testExecuteReturnsInvalidParamsForWrongFieldType(): void {
    $storageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storageDefinition->method('getType')->willReturn('string');

    $fieldConfig = $this->createMock(FieldConfigInterface::class);
    $fieldConfig->method('getFieldStorageDefinition')->willReturn($storageDefinition);

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willReturn([
      'field_paragraphs' => $fieldConfig,
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $tool = $this->buildTool($entityTypeManager, $entityFieldManager);
    $array = $tool->execute($this->defaultInput)->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertStringContainsString('string', $array['error']['message']);
  }

  /**
   * Tests the happy path when both displays already exist.
   */
  public function testExecuteConfiguresBothDisplaysWhenExisting(): void {
    $fieldConfig = $this->buildErrFieldConfig();

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willReturn([
      'field_paragraphs' => $fieldConfig,
    ]);

    $formDisplay = $this->createMock(EntityFormDisplayInterface::class);
    $formDisplay->expects($this->once())->method('setComponent')->with(
      'field_paragraphs',
      $this->callback(function (array $component): bool {
        return $component['type'] === 'layout_paragraphs'
          && isset($component['settings']['nesting_depth'])
          && $component['settings']['nesting_depth'] === 0
          && isset($component['settings']['require_layouts'])
          && $component['settings']['require_layouts'] === FALSE;
      }),
    );
    $formDisplay->expects($this->once())->method('save');

    $viewDisplay = $this->createMock(EntityViewDisplayInterface::class);
    $viewDisplay->expects($this->once())->method('setComponent')->with(
      'field_paragraphs',
      $this->callback(function (array $component): bool {
        return $component['type'] === 'layout_paragraphs';
      }),
    );
    $viewDisplay->expects($this->once())->method('save');

    $formDisplayStorage = $this->createMock(EntityStorageInterface::class);
    $formDisplayStorage->method('load')->willReturn($formDisplay);

    $viewDisplayStorage = $this->createMock(EntityStorageInterface::class);
    $viewDisplayStorage->method('load')->willReturn($viewDisplay);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->willReturnCallback(function (string $entityType) use ($formDisplayStorage, $viewDisplayStorage): EntityStorageInterface {
        return match ($entityType) {
          'entity_form_display' => $formDisplayStorage,
          'entity_view_display' => $viewDisplayStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });

    $tool = $this->buildTool($entityTypeManager, $entityFieldManager);
    $array = $tool->execute($this->defaultInput)->toArray();

    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame('node', $array['result']['entity_type']);
    $this->assertSame('article', $array['result']['bundle']);
    $this->assertSame('field_paragraphs', $array['result']['field_name']);
    $this->assertSame('layout_paragraphs', $array['result']['widget_type']);
    $this->assertSame('layout_paragraphs', $array['result']['formatter_type']);
  }

  /**
   * Tests that when a display is absent, create() is used instead of load().
   */
  public function testExecuteCreatesDisplayWhenNotFound(): void {
    $fieldConfig = $this->buildErrFieldConfig();

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willReturn([
      'field_paragraphs' => $fieldConfig,
    ]);

    $formDisplay = $this->createMock(EntityFormDisplayInterface::class);
    $formDisplay->method('setComponent');
    $formDisplay->method('save');

    $viewDisplay = $this->createMock(EntityViewDisplayInterface::class);
    $viewDisplay->method('setComponent');
    $viewDisplay->method('save');

    $formDisplayStorage = $this->createMock(EntityStorageInterface::class);
    // Return NULL to trigger create() path.
    $formDisplayStorage->method('load')->willReturn(NULL);
    $formDisplayStorage->method('create')->willReturn($formDisplay);

    $viewDisplayStorage = $this->createMock(EntityStorageInterface::class);
    $viewDisplayStorage->method('load')->willReturn(NULL);
    $viewDisplayStorage->method('create')->willReturn($viewDisplay);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->willReturnCallback(function (string $entityType) use ($formDisplayStorage, $viewDisplayStorage): EntityStorageInterface {
        return match ($entityType) {
          'entity_form_display' => $formDisplayStorage,
          'entity_view_display' => $viewDisplayStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });

    $tool = $this->buildTool($entityTypeManager, $entityFieldManager);
    $array = $tool->execute($this->defaultInput)->toArray();

    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame('layout_paragraphs', $array['result']['widget_type']);
  }

  /**
   * Tests that an EntityStorageException from save() returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnEntityStorageException(): void {
    $fieldConfig = $this->buildErrFieldConfig();

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willReturn([
      'field_paragraphs' => $fieldConfig,
    ]);

    $formDisplay = $this->createMock(EntityFormDisplayInterface::class);
    $formDisplay->method('setComponent');
    $formDisplay->method('save')->willThrowException(new EntityStorageException('DB error'));

    $formDisplayStorage = $this->createMock(EntityStorageInterface::class);
    $formDisplayStorage->method('load')->willReturn($formDisplay);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($formDisplayStorage);

    $tool = $this->buildTool($entityTypeManager, $entityFieldManager);
    $array = $tool->execute($this->defaultInput)->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that an EntityStorageException from view display save() returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnViewDisplaySaveException(): void {
    $fieldConfig = $this->buildErrFieldConfig();

    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willReturn([
      'field_paragraphs' => $fieldConfig,
    ]);

    $formDisplay = $this->createMock(EntityFormDisplayInterface::class);
    $formDisplay->method('setComponent');
    $formDisplay->method('save');

    $viewDisplay = $this->createMock(EntityViewDisplayInterface::class);
    $viewDisplay->method('setComponent');
    $viewDisplay->method('save')->willThrowException(new EntityStorageException('View DB error'));

    $formDisplayStorage = $this->createMock(EntityStorageInterface::class);
    $formDisplayStorage->method('load')->willReturn($formDisplay);

    $viewDisplayStorage = $this->createMock(EntityStorageInterface::class);
    $viewDisplayStorage->method('load')->willReturn($viewDisplay);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->willReturnCallback(function (string $entityType) use ($formDisplayStorage, $viewDisplayStorage): EntityStorageInterface {
        return match ($entityType) {
          'entity_form_display' => $formDisplayStorage,
          'entity_view_display' => $viewDisplayStorage,
          default => $this->createMock(EntityStorageInterface::class),
        };
      });

    $tool = $this->buildTool($entityTypeManager, $entityFieldManager);
    $array = $tool->execute($this->defaultInput)->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that an unexpected Throwable returns INTERNAL_ERROR.
   */
  public function testExecuteReturnsInternalErrorOnUnexpectedThrowable(): void {
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityFieldManager->method('getFieldDefinitions')->willThrowException(new \RuntimeException('Unexpected'));

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $tool = $this->buildTool($entityTypeManager, $entityFieldManager);
    $array = $tool->execute($this->defaultInput)->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INTERNAL_ERROR, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

}
