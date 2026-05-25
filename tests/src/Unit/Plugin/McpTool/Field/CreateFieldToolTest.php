<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp\Unit\Plugin\McpTool\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\FieldType\FieldTypeCollector;
use Drupal\drupal_mcp\FieldType\FieldTypeProviderInterface;
use Drupal\drupal_mcp\Plugin\McpTool\Field\CreateFieldTool;
use Drupal\drupal_mcp\ValueObject\McpError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the media-convention security guard in CreateFieldTool.
 *
 * The guard short-circuits before any static entity API calls, so these tests
 * require no Drupal bootstrap.
 */
#[CoversClass(CreateFieldTool::class)]
#[Group('drupal_mcp')]
final class CreateFieldToolTest extends UnitTestCase {

  /**
   * The media-convention guard error substring expected in the error message.
   */
  private const MEDIA_GUARD_SUBSTRING = 'entity_reference';

  /**
   * Builds a CreateFieldTool with dummy dependencies.
   *
   * The entity type manager is never reached when the media-convention guard
   * fires, so it is mocked as an unused stub.
   *
   * @return \Drupal\drupal_mcp\Plugin\McpTool\Field\CreateFieldTool
   *   The tool under test.
   */
  private function buildTool(): CreateFieldTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $provider = $this->createMock(FieldTypeProviderInterface::class);
    $provider->method('getSupportedTypes')->willReturn([
      'entity_reference', 'file', 'image', 'text_long',
    ]);
    $collector = new FieldTypeCollector([$provider]);

    return new CreateFieldTool(
      $entityTypeManager,
      $logger,
      $collector,
    );
  }

  /**
   * Provides field types that are forbidden on node entity type.
   *
   * @return array<string, array{string}>
   *   Keyed by description.
   */
  public static function forbiddenNodeFieldTypeProvider(): array {
    return [
      'image field on node' => ['image'],
      'file field on node'  => ['file'],
    ];
  }

  /**
   * Tests that image/file field types on nodes are rejected with an error.
   *
   * @param string $fieldType
   *   The forbidden field type.
   */
  #[DataProvider('forbiddenNodeFieldTypeProvider')]
  public function testExecuteRejectsImageOrFileFieldOnNode(string $fieldType): void {
    $tool = $this->buildTool();

    $response = $tool->execute([
      'entity_type' => 'node',
      'bundle'      => 'article',
      'field_name'  => 'field_media',
      'field_type'  => $fieldType,
      'label'       => 'Media',
    ]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array, 'Expected an error response for forbidden field type on node.');
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    // The error message must reference entity_reference as the recommended path.
    $this->assertStringContainsString(self::MEDIA_GUARD_SUBSTRING, $array['error']['message']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that the node-specific guard does not fire when entity_type is media.
   *
   * When entity_type=media the guard is bypassed, and the code proceeds to the
   * static FieldConfig/FieldStorageConfig calls which require a full Drupal
   * container.  In a unit test context those calls throw, which is caught by
   * the internal \Throwable handler and returned as INTERNAL_ERROR — never as
   * the media-convention INVALID_PARAMS error.
   */
  public function testExecuteDoesNotApplyNodeGuardWhenEntityTypeIsMedia(): void {
    $tool = $this->buildTool();

    $response = $tool->execute([
      'entity_type' => 'media',
      'bundle'      => 'image',
      'field_name'  => 'field_caption',
      'field_type'  => 'image',
      'label'       => 'Image',
    ]);

    $array = $response->toArray();

    // The response must NOT carry the media-convention guard message.
    if (isset($array['error'])) {
      $this->assertStringNotContainsString(
        self::MEDIA_GUARD_SUBSTRING,
        $array['error']['message'],
        'The media-convention guard must not fire when entity_type is not "node".',
      );
      // Any error that does occur must be INTERNAL_ERROR (from the static-call
      // fallback), never INVALID_PARAMS from the node guard.
      $this->assertNotSame(
        McpError::INVALID_PARAMS,
        $array['error']['code'],
        'Error code must not be INVALID_PARAMS — the node-specific guard must not fire for entity_type=media.',
      );
    }
  }

}
