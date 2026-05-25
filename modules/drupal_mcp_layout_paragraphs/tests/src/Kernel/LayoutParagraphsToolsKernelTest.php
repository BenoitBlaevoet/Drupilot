<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_layout_paragraphs\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Field\ConfigureLayoutParagraphsDisplayTool;
use Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Layout\ListLayoutsTool;
use Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Paragraph\ConfigureParagraphTypeLayoutTool;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Integration tests for the layout_paragraphs MCP tools.
 *
 * Verifies layout discovery, paragraph type layout configuration, and field
 * display wiring against a real Drupal kernel.
 */
#[CoversClass(ListLayoutsTool::class)]
#[CoversClass(ConfigureParagraphTypeLayoutTool::class)]
#[CoversClass(ConfigureLayoutParagraphsDisplayTool::class)]
#[Group('drupal_mcp')]
#[RunTestsInSeparateProcesses]
final class LayoutParagraphsToolsKernelTest extends KernelTestBase {

  /**
   * The bundle machine name used for display tests.
   */
  private const TEST_BUNDLE = 'test_bundle';

  /**
   * The paragraphs field name used for display tests.
   */
  private const TEST_FIELD = 'field_sections';

  /**
   * {@inheritdoc}
   *
   * @var array<int, string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'field_ui',
    'text',
    'node',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'layout_discovery',
    'layout_paragraphs',
    'drupal_mcp',
    'drupal_mcp_paragraphs',
    'drupal_mcp_layout_paragraphs',
  ];

  /**
   * The layout list tool.
   */
  private ListLayoutsTool $layoutListTool;

  /**
   * The configure layout tool.
   */
  private ConfigureParagraphTypeLayoutTool $configureLayoutTool;

  /**
   * The configure display tool.
   */
  private ConfigureLayoutParagraphsDisplayTool $configureDisplayTool;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['drupal_mcp']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['node', 'field']);

    NodeType::create([
      'type' => self::TEST_BUNDLE,
      'name' => 'Test bundle',
    ])->save();

    // Create an entity_reference_revisions field storage on node.
    FieldStorageConfig::create([
      'field_name' => self::TEST_FIELD,
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'field_name' => self::TEST_FIELD,
      'entity_type' => 'node',
      'bundle' => self::TEST_BUNDLE,
      'label' => 'Sections',
    ])->save();

    // Create the default form and view displays so they exist before the test.
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => self::TEST_BUNDLE,
      'mode' => 'default',
      'status' => TRUE,
    ])->save();

    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => self::TEST_BUNDLE,
      'mode' => 'default',
      'status' => TRUE,
    ])->save();

    /** @var \Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Layout\ListLayoutsTool $layoutListTool */
    $layoutListTool = $this->container->get('drupal_mcp_layout_paragraphs.tool.layout_list');
    $this->layoutListTool = $layoutListTool;

    /** @var \Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Paragraph\ConfigureParagraphTypeLayoutTool $configureLayoutTool */
    $configureLayoutTool = $this->container->get('drupal_mcp_layout_paragraphs.tool.paragraph_type_configure_layout');
    $this->configureLayoutTool = $configureLayoutTool;

    /** @var \Drupal\drupal_mcp_layout_paragraphs\Plugin\McpTool\Field\ConfigureLayoutParagraphsDisplayTool $configureDisplayTool */
    $configureDisplayTool = $this->container->get('drupal_mcp_layout_paragraphs.tool.paragraph_field_configure_layout_display');
    $this->configureDisplayTool = $configureDisplayTool;
  }

  /**
   * Tests that layout_list returns layouts including the built-in layout_onecol.
   */
  public function testLayoutListReturnsAvailableLayouts(): void {
    $response = $this->layoutListTool->execute([]);
    $array = $response->toArray();

    $this->assertArrayNotHasKey('error', $array, 'layout_list should succeed.');
    $this->assertGreaterThan(0, $array['result']['count'], 'At least one layout must be registered.');

    $ids = array_column($array['result']['items'], 'id');
    $this->assertContains('layout_onecol', $ids, 'layout_onecol must be present.');

    // Each layout must have id, label, category, and regions.
    foreach ($array['result']['items'] as $layout) {
      $this->assertArrayHasKey('id', $layout);
      $this->assertArrayHasKey('label', $layout);
      $this->assertArrayHasKey('category', $layout);
      $this->assertIsString($layout['category']);
      $this->assertArrayHasKey('regions', $layout);
      $this->assertIsArray($layout['regions']);
    }
  }

  /**
   * Tests the full lifecycle: create paragraph type → configure layout.
   */
  public function testConfigureParagraphTypeLayout(): void {
    // Create a paragraph type first.
    /** @var \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\CreateParagraphTypeTool $createTool */
    $createTool = $this->container->get('drupal_mcp_paragraphs.tool.paragraph_type_create');
    $createResponse = $createTool->execute([
      'machine_name' => 'layout_section',
      'label' => 'Layout Section',
    ]);
    $this->assertArrayNotHasKey('error', $createResponse->toArray());

    // Configure the layout_paragraphs behavior on it.
    $configureResponse = $this->configureLayoutTool->execute([
      'machine_name' => 'layout_section',
      'available_layouts' => ['layout_onecol'],
    ]);
    $configureArray = $configureResponse->toArray();

    $this->assertArrayNotHasKey('error', $configureArray, 'Configure layout should succeed.');
    $this->assertSame('layout_section', $configureArray['result']['machine_name']);
    $this->assertTrue($configureArray['result']['enabled']);
    $this->assertSame(['layout_onecol'], $configureArray['result']['available_layouts']);

    // Load the entity and assert the behavior_plugins property directly.
    /** @var \Drupal\Core\Entity\EntityStorageInterface $paragraphTypeStorage */
    $paragraphTypeStorage = $this->container->get('entity_type.manager')
      ->getStorage('paragraphs_type');
    $paragraphType = $paragraphTypeStorage->load('layout_section');

    $this->assertNotNull($paragraphType);
    $this->assertInstanceOf(ParagraphsTypeInterface::class, $paragraphType);
    $behaviorPlugins = $paragraphType->get('behavior_plugins');
    $this->assertIsArray($behaviorPlugins);
    $this->assertArrayHasKey('layout_paragraphs', $behaviorPlugins);
    $this->assertTrue($behaviorPlugins['layout_paragraphs']['enabled']);
    $this->assertSame(['layout_onecol'], $behaviorPlugins['layout_paragraphs']['available_layouts']);
  }

  /**
   * Tests that configure layout returns INVALID_PARAMS for unknown paragraph type.
   */
  public function testConfigureLayoutReturnsInvalidParamsForMissingType(): void {
    $response = $this->configureLayoutTool->execute([
      'machine_name' => 'nonexistent_type',
      'available_layouts' => ['layout_onecol'],
    ]);
    $array = $response->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(-32602, $array['error']['code']);
  }

  /**
   * Tests that configure layout returns INVALID_PARAMS for unknown layout IDs.
   */
  public function testConfigureLayoutReturnsInvalidParamsForUnknownLayoutId(): void {
    /** @var \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\CreateParagraphTypeTool $createTool */
    $createTool = $this->container->get('drupal_mcp_paragraphs.tool.paragraph_type_create');
    $createTool->execute(['machine_name' => 'para_for_bad_layout', 'label' => 'Test']);

    $response = $this->configureLayoutTool->execute([
      'machine_name' => 'para_for_bad_layout',
      'available_layouts' => ['this_layout_does_not_exist'],
    ]);
    $array = $response->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(-32602, $array['error']['code']);
  }

  /**
   * Tests that configuring a field display sets the correct widget and formatter.
   */
  public function testConfigureLayoutParagraphsDisplaySetsCorrectTypes(): void {
    $response = $this->configureDisplayTool->execute([
      'entity_type' => 'node',
      'bundle' => self::TEST_BUNDLE,
      'field_name' => self::TEST_FIELD,
      'nesting_depth' => 1,
    ]);
    $array = $response->toArray();

    $this->assertArrayNotHasKey('error', $array, 'Configure display should succeed.');
    $this->assertSame('layout_paragraphs', $array['result']['widget_type']);
    $this->assertSame('layout_paragraphs', $array['result']['formatter_type']);

    // Assert the form display widget was set correctly.
    $formDisplay = EntityFormDisplay::load('node.' . self::TEST_BUNDLE . '.default');
    $this->assertNotNull($formDisplay);
    $component = $formDisplay->getComponent(self::TEST_FIELD);
    $this->assertNotNull($component);
    $this->assertSame('layout_paragraphs', $component['type']);
    $this->assertSame(1, $component['settings']['nesting_depth']);

    // Assert the view display formatter was set correctly.
    $viewDisplay = EntityViewDisplay::load('node.' . self::TEST_BUNDLE . '.default');
    $this->assertNotNull($viewDisplay);
    $viewComponent = $viewDisplay->getComponent(self::TEST_FIELD);
    $this->assertNotNull($viewComponent);
    $this->assertSame('layout_paragraphs', $viewComponent['type']);
  }

  /**
   * Tests that configuring display returns INVALID_PARAMS for a base field.
   *
   * 'title' is a base field (not FieldConfigInterface), so the
   * "not a configurable field" guard fires.
   */
  public function testConfigureDisplayReturnsInvalidParamsForBaseField(): void {
    $response = $this->configureDisplayTool->execute([
      'entity_type' => 'node',
      'bundle' => self::TEST_BUNDLE,
      'field_name' => 'title',
    ]);
    $array = $response->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(-32602, $array['error']['code']);
  }

  /**
   * Tests that configuring display returns INVALID_PARAMS for a wrong field type.
   *
   * A configurable field that is not entity_reference_revisions triggers the
   * field-type guard.
   */
  public function testConfigureDisplayReturnsInvalidParamsForNonErrConfigurableField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_body_text',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_body_text',
      'entity_type' => 'node',
      'bundle' => self::TEST_BUNDLE,
      'label' => 'Body text',
    ])->save();

    $response = $this->configureDisplayTool->execute([
      'entity_type' => 'node',
      'bundle' => self::TEST_BUNDLE,
      'field_name' => 'field_body_text',
    ]);
    $array = $response->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(-32602, $array['error']['code']);
    $this->assertStringContainsString('text_long', $array['error']['message']);
  }

  /**
   * Tests that all three tools are correctly registered as services.
   */
  public function testAllToolsAreRegisteredInContainer(): void {
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(
      ListLayoutsTool::class,
      $this->container->get('drupal_mcp_layout_paragraphs.tool.layout_list'),
    );
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(
      ConfigureParagraphTypeLayoutTool::class,
      $this->container->get('drupal_mcp_layout_paragraphs.tool.paragraph_type_configure_layout'),
    );
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(
      ConfigureLayoutParagraphsDisplayTool::class,
      $this->container->get('drupal_mcp_layout_paragraphs.tool.paragraph_field_configure_layout_display'),
    );
  }

}
