<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp_telephone\Unit\FieldType;

use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp_telephone\FieldType\TelephoneFieldTypeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests TelephoneFieldTypeProvider.
 */
#[CoversClass(TelephoneFieldTypeProvider::class)]
#[Group('drupal_mcp')]
final class TelephoneFieldTypeProviderTest extends UnitTestCase {

  /**
   * Tests that getSupportedTypes() returns exactly ['telephone'].
   */
  public function testGetSupportedTypesReturnsTelephone(): void {
    $provider = new TelephoneFieldTypeProvider();

    $this->assertSame(['telephone'], $provider->getSupportedTypes());
  }

}
