<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot\Unit\ValueObject;

use Drupal\Tests\UnitTestCase;
use Drupal\drupilot\ValueObject\McpError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests McpError constants and serialisation.
 */
#[CoversClass(McpError::class)]
#[Group('drupilot')]
final class McpErrorTest extends UnitTestCase {

  /**
   * Verifies the JSON-RPC standard PARSE_ERROR code.
   */
  public function testParseErrorConstantValue(): void {
    /** @var int $value */
    $value = McpError::PARSE_ERROR;
    $this->assertSame(-32700, $value);
  }

  /**
   * Verifies the JSON-RPC standard INVALID_REQUEST code.
   */
  public function testInvalidRequestConstantValue(): void {
    /** @var int $value */
    $value = McpError::INVALID_REQUEST;
    $this->assertSame(-32600, $value);
  }

  /**
   * Verifies the JSON-RPC standard METHOD_NOT_FOUND code.
   */
  public function testMethodNotFoundConstantValue(): void {
    /** @var int $value */
    $value = McpError::METHOD_NOT_FOUND;
    $this->assertSame(-32601, $value);
  }

  /**
   * Verifies the JSON-RPC standard INVALID_PARAMS code.
   */
  public function testInvalidParamsConstantValue(): void {
    /** @var int $value */
    $value = McpError::INVALID_PARAMS;
    $this->assertSame(-32602, $value);
  }

  /**
   * Verifies the JSON-RPC standard INTERNAL_ERROR code.
   */
  public function testInternalErrorConstantValue(): void {
    /** @var int $value */
    $value = McpError::INTERNAL_ERROR;
    $this->assertSame(-32603, $value);
  }

  /**
   * Verifies the module-specific TOOL_DISABLED error code.
   */
  public function testToolDisabledConstantValue(): void {
    /** @var int $value */
    $value = McpError::TOOL_DISABLED;
    $this->assertSame(-32000, $value);
  }

  /**
   * Verifies the module-specific TOOL_NOT_FOUND error code.
   */
  public function testToolNotFoundConstantValue(): void {
    /** @var int $value */
    $value = McpError::TOOL_NOT_FOUND;
    $this->assertSame(-32001, $value);
  }

  /**
   * Tests that toArray() contains the code and message keys.
   */
  public function testToArrayContainsCodeAndMessage(): void {
    $error = new McpError(-32601, 'Method not found.');
    $array = $error->toArray();

    $this->assertArrayHasKey('code', $array);
    $this->assertArrayHasKey('message', $array);
    $this->assertSame(-32601, $array['code']);
    $this->assertSame('Method not found.', $array['message']);
  }

  /**
   * Tests that toArray() omits the data key when data is null.
   */
  public function testToArrayOmitsDataKeyWhenNull(): void {
    $error = new McpError(-32603, 'Internal error.');
    $array = $error->toArray();

    $this->assertArrayNotHasKey('data', $array);
  }

  /**
   * Tests that toArray() includes the data key when data is provided.
   */
  public function testToArrayIncludesDataKeyWhenProvided(): void {
    $error = new McpError(-32602, 'Invalid params.', ['field' => 'machine_name']);
    $array = $error->toArray();

    $this->assertArrayHasKey('data', $array);
    $this->assertSame(['field' => 'machine_name'], $array['data']);
  }

}
