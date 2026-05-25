<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_daterange\Unit\FieldType;

use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp_daterange\FieldType\DateRangeFieldTypeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DateRangeFieldTypeProvider.
 */
#[CoversClass(DateRangeFieldTypeProvider::class)]
#[Group('drupal_mcp')]
final class DateRangeFieldTypeProviderTest extends UnitTestCase {

  /**
   * Tests that getSupportedTypes() returns exactly ['daterange'].
   */
  public function testGetSupportedTypesReturnsDaterange(): void {
    $provider = new DateRangeFieldTypeProvider();

    $this->assertSame(['daterange'], $provider->getSupportedTypes());
  }

}
