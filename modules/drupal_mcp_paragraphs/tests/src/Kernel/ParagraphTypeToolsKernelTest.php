<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_paragraphs\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\CreateParagraphTypeTool;
use Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\DeleteParagraphTypeTool;
use Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\ListParagraphTypesTool;
use Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\UpdateParagraphTypeTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Integration tests for the paragraph type management tools.
 *
 * Verifies the full create → list → update → delete lifecycle against a real
 * Drupal kernel with paragraphs installed.
 */
#[CoversClass(CreateParagraphTypeTool::class)]
#[CoversClass(UpdateParagraphTypeTool::class)]
#[CoversClass(DeleteParagraphTypeTool::class)]
#[CoversClass(ListParagraphTypesTool::class)]
#[Group('drupal_mcp')]
#[RunTestsInSeparateProcesses]
final class ParagraphTypeToolsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<int, string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'drupal_mcp',
    'drupal_mcp_paragraphs',
  ];

  /**
   * The create tool.
   */
  private CreateParagraphTypeTool $createTool;

  /**
   * The update tool.
   */
  private UpdateParagraphTypeTool $updateTool;

  /**
   * The delete tool.
   */
  private DeleteParagraphTypeTool $deleteTool;

  /**
   * The list tool.
   */
  private ListParagraphTypesTool $listTool;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['drupal_mcp']);

    /** @var \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\CreateParagraphTypeTool $createTool */
    $createTool = $this->container->get('drupal_mcp_paragraphs.tool.paragraph_type_create');
    $this->createTool = $createTool;

    /** @var \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\UpdateParagraphTypeTool $updateTool */
    $updateTool = $this->container->get('drupal_mcp_paragraphs.tool.paragraph_type_update');
    $this->updateTool = $updateTool;

    /** @var \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\DeleteParagraphTypeTool $deleteTool */
    $deleteTool = $this->container->get('drupal_mcp_paragraphs.tool.paragraph_type_delete');
    $this->deleteTool = $deleteTool;

    /** @var \Drupal\drupal_mcp_paragraphs\Plugin\McpTool\Paragraph\ListParagraphTypesTool $listTool */
    $listTool = $this->container->get('drupal_mcp_paragraphs.tool.paragraph_type_list');
    $this->listTool = $listTool;
  }

  /**
   * Tests the full lifecycle: create → list → update → delete.
   */
  public function testCreateThenListThenUpdateThenDelete(): void {
    // Step 1: Create.
    $createResponse = $this->createTool->execute([
      'machine_name' => 'test_para',
      'label' => 'Test Paragraph',
      'description' => 'Initial description',
    ]);

    $createArray = $createResponse->toArray();
    $this->assertArrayNotHasKey('error', $createArray, 'Create should succeed.');
    $this->assertSame('test_para', $createArray['result']['machine_name']);

    // Step 2: List — the new type must appear.
    $listResponse = $this->listTool->execute([]);
    $listArray = $listResponse->toArray();
    $this->assertArrayNotHasKey('error', $listArray, 'List should succeed.');

    $machineNames = array_column($listArray['result']['paragraph_types'], 'machine_name');
    $this->assertContains('test_para', $machineNames, 'Created type must appear in list.');

    // Step 3: Update — change the label.
    $updateResponse = $this->updateTool->execute([
      'machine_name' => 'test_para',
      'label' => 'Updated Paragraph',
    ]);

    $updateArray = $updateResponse->toArray();
    $this->assertArrayNotHasKey('error', $updateArray, 'Update should succeed.');
    $this->assertSame('test_para', $updateArray['result']['machine_name']);

    // Step 4: Delete.
    $deleteResponse = $this->deleteTool->execute([
      'machine_name' => 'test_para',
    ]);

    $deleteArray = $deleteResponse->toArray();
    $this->assertArrayNotHasKey('error', $deleteArray, 'Delete should succeed.');
    $this->assertTrue($deleteArray['result']['deleted']);

    // Step 5: List — the type must no longer appear.
    $listAfterDelete = $this->listTool->execute([]);
    $listAfterArray = $listAfterDelete->toArray();
    $machineNamesAfter = array_column($listAfterArray['result']['paragraph_types'], 'machine_name');
    $this->assertNotContains('test_para', $machineNamesAfter, 'Deleted type must not appear in list.');
  }

  /**
   * Tests that the create tool services are correctly wired in the container.
   */
  public function testCreateToolIsRegisteredInContainer(): void {
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(
      CreateParagraphTypeTool::class,
      $this->container->get('drupal_mcp_paragraphs.tool.paragraph_type_create'),
    );
  }

}
