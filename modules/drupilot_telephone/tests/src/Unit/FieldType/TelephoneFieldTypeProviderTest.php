<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot_telephone\Unit\FieldType;

use Drupal\Tests\UnitTestCase;
use Drupal\drupilot_telephone\FieldType\TelephoneFieldTypeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests TelephoneFieldTypeProvider.
 */
#[CoversClass(TelephoneFieldTypeProvider::class)]
#[Group('drupilot')]
final class TelephoneFieldTypeProviderTest extends UnitTestCase {

  /**
   * Tests that getSupportedTypes() returns exactly ['telephone'].
   */
  public function testGetSupportedTypesReturnsTelephone(): void {
    $provider = new TelephoneFieldTypeProvider();

    $this->assertSame(['telephone'], $provider->getSupportedTypes());
  }

}
