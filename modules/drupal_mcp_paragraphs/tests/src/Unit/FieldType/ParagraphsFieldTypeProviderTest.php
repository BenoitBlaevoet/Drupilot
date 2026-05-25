<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_paragraphs\Unit\FieldType;

use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp_paragraphs\FieldType\ParagraphsFieldTypeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ParagraphsFieldTypeProvider.
 */
#[CoversClass(ParagraphsFieldTypeProvider::class)]
#[Group('drupal_mcp')]
final class ParagraphsFieldTypeProviderTest extends UnitTestCase {

  /**
   * Tests that getSupportedTypes() returns exactly ['entity_reference_revisions'].
   */
  public function testGetSupportedTypesReturnsEntityReferenceRevisions(): void {
    $provider = new ParagraphsFieldTypeProvider();

    $this->assertSame(['entity_reference_revisions'], $provider->getSupportedTypes());
  }

}
