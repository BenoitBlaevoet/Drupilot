<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot_field_group\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\CreateFieldGroupTool;
use Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\DeleteFieldGroupTool;
use Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\ListFieldGroupsTool;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Integration tests for the field group management tools.
 *
 * Verifies create → list → delete lifecycle against a real Drupal kernel
 * with the field_group module installed.
 */
#[CoversClass(CreateFieldGroupTool::class)]
#[CoversClass(DeleteFieldGroupTool::class)]
#[CoversClass(ListFieldGroupsTool::class)]
#[Group('drupilot')]
#[RunTestsInSeparateProcesses]
final class FieldGroupToolsKernelTest extends KernelTestBase {

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
    'field_group',
    'drupilot',
    'drupilot_field_group',
  ];

  /**
   * The create tool.
   */
  private CreateFieldGroupTool $createTool;

  /**
   * The delete tool.
   */
  private DeleteFieldGroupTool $deleteTool;

  /**
   * The list tool.
   */
  private ListFieldGroupsTool $listTool;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['drupilot']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'field']);

    // Create a 'page' node type so its form display can exist.
    NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
    ])->save();

    // Create (and save) the default form display for node.page.
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'page',
      'mode' => 'default',
      'status' => TRUE,
    ])->save();

    /** @var \Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\CreateFieldGroupTool $createTool */
    $createTool = $this->container->get('drupilot_field_group.tool.field_group_create');
    $this->createTool = $createTool;

    /** @var \Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\DeleteFieldGroupTool $deleteTool */
    $deleteTool = $this->container->get('drupilot_field_group.tool.field_group_delete');
    $this->deleteTool = $deleteTool;

    /** @var \Drupal\drupilot_field_group\Plugin\McpTool\FieldGroup\ListFieldGroupsTool $listTool */
    $listTool = $this->container->get('drupilot_field_group.tool.field_group_list');
    $this->listTool = $listTool;
  }

  /**
   * Tests the full lifecycle: create → list → delete.
   */
  public function testCreateThenListThenDelete(): void {
    // Step 1: Create.
    $createResponse = $this->createTool->execute([
      'entity_type' => 'node',
      'bundle' => 'page',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
      'label' => 'Meta',
      'format_type' => 'details',
    ]);

    $createArray = $createResponse->toArray();
    $this->assertArrayNotHasKey('error', $createArray, 'Create should succeed.');
    $this->assertSame('group_meta', $createArray['result']['group_name']);

    // Step 2: List — the group must appear.
    $listResponse = $this->listTool->execute([
      'entity_type' => 'node',
      'bundle' => 'page',
      'display_mode' => 'form',
    ]);

    $listArray = $listResponse->toArray();
    $this->assertArrayNotHasKey('error', $listArray, 'List should succeed.');
    $this->assertGreaterThan(0, $listArray['result']['count']);

    $groupNames = array_column($listArray['result']['groups'], 'group_name');
    $this->assertContains('group_meta', $groupNames, 'Created group must appear in list.');

    // Step 3: Delete.
    $deleteResponse = $this->deleteTool->execute([
      'entity_type' => 'node',
      'bundle' => 'page',
      'display_mode' => 'form',
      'group_name' => 'group_meta',
    ]);

    $deleteArray = $deleteResponse->toArray();
    $this->assertArrayNotHasKey('error', $deleteArray, 'Delete should succeed.');
    $this->assertTrue($deleteArray['result']['deleted']);

    // Step 4: List — the group must no longer appear.
    $listAfterResponse = $this->listTool->execute([
      'entity_type' => 'node',
      'bundle' => 'page',
      'display_mode' => 'form',
    ]);

    $listAfterArray = $listAfterResponse->toArray();
    $groupNamesAfter = array_column($listAfterArray['result']['groups'], 'group_name');
    $this->assertNotContains('group_meta', $groupNamesAfter, 'Deleted group must not appear in list.');
  }

  /**
   * Tests the full lifecycle on a view display: create → list → delete.
   */
  public function testCreateAndDeleteViewDisplayGroup(): void {
    // Create the default view display for node.page.
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'page',
      'mode' => 'default',
      'status' => TRUE,
    ])->save();

    // Step 1: Create on view display.
    $createResponse = $this->createTool->execute([
      'entity_type' => 'node',
      'bundle' => 'page',
      'display_mode' => 'view',
      'group_name' => 'group_sidebar',
      'label' => 'Sidebar',
      'format_type' => 'fieldset',
    ]);

    $createArray = $createResponse->toArray();
    $this->assertArrayNotHasKey('error', $createArray, 'Create on view display should succeed.');
    $this->assertSame('group_sidebar', $createArray['result']['group_name']);

    // Step 2: List — group must appear.
    $listResponse = $this->listTool->execute([
      'entity_type' => 'node',
      'bundle' => 'page',
      'display_mode' => 'view',
    ]);

    $listArray = $listResponse->toArray();
    $this->assertArrayNotHasKey('error', $listArray);
    $groupNames = array_column($listArray['result']['groups'], 'group_name');
    $this->assertContains('group_sidebar', $groupNames);

    // Step 3: Delete.
    $deleteResponse = $this->deleteTool->execute([
      'entity_type' => 'node',
      'bundle' => 'page',
      'display_mode' => 'view',
      'group_name' => 'group_sidebar',
    ]);

    $deleteArray = $deleteResponse->toArray();
    $this->assertArrayNotHasKey('error', $deleteArray);
    $this->assertTrue($deleteArray['result']['deleted']);

    // Step 4: List — group must be gone.
    $listAfterResponse = $this->listTool->execute([
      'entity_type' => 'node',
      'bundle' => 'page',
      'display_mode' => 'view',
    ]);

    $listAfterArray = $listAfterResponse->toArray();
    $groupNamesAfter = array_column($listAfterArray['result']['groups'], 'group_name');
    $this->assertNotContains('group_sidebar', $groupNamesAfter);
  }

  /**
   * Tests that the create tool is registered as a service.
   */
  public function testCreateToolIsRegisteredInContainer(): void {
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(
      CreateFieldGroupTool::class,
      $this->container->get('drupilot_field_group.tool.field_group_create'),
    );
  }

}
