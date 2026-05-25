<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_link\Unit\FieldType;

use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp_link\FieldType\LinkFieldTypeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests LinkFieldTypeProvider.
 */
#[CoversClass(LinkFieldTypeProvider::class)]
#[Group('drupal_mcp')]
final class LinkFieldTypeProviderTest extends UnitTestCase {

  /**
   * Tests that getSupportedTypes() returns exactly ['link'].
   */
  public function testGetSupportedTypesReturnsLink(): void {
    $provider = new LinkFieldTypeProvider();

    $this->assertSame(['link'], $provider->getSupportedTypes());
  }

}
