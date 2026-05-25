<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot_daterange\Unit\FieldType;

use Drupal\Tests\UnitTestCase;
use Drupal\drupilot_daterange\FieldType\DateRangeFieldTypeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DateRangeFieldTypeProvider.
 */
#[CoversClass(DateRangeFieldTypeProvider::class)]
#[Group('drupilot')]
final class DateRangeFieldTypeProviderTest extends UnitTestCase {

  /**
   * Tests that getSupportedTypes() returns exactly ['daterange'].
   */
  public function testGetSupportedTypesReturnsDaterange(): void {
    $provider = new DateRangeFieldTypeProvider();

    $this->assertSame(['daterange'], $provider->getSupportedTypes());
  }

}
