<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp\Unit\ValueObject;

use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests McpResponse factory methods and serialisation.
 */
#[CoversClass(McpResponse::class)]
#[Group('drupal_mcp')]
final class McpResponseTest extends UnitTestCase {

  /**
   * Tests that success() produces a response with a result and no error.
   */
  public function testSuccessProducesResponseWithResult(): void {
    $response = McpResponse::success('1', ['tools' => []]);

    $this->assertSame(['tools' => []], $response->result);
    $this->assertNull($response->error);
  }

  /**
   * Tests that success() produces a response with jsonrpc = "2.0".
   */
  public function testSuccessProducesResponseWithJsonrpc20(): void {
    $response = McpResponse::success('1', []);

    $this->assertSame('2.0', $response->jsonrpc);
  }

  /**
   * Tests that success().toArray() contains result key and no error key.
   */
  public function testSuccessToArrayContainsResultKeyAndNoErrorKey(): void {
    $response = McpResponse::success('req-1', ['foo' => 'bar']);
    $array = $response->toArray();

    $this->assertArrayHasKey('result', $array);
    $this->assertArrayNotHasKey('error', $array);
    $this->assertSame(['foo' => 'bar'], $array['result']);
  }

  /**
   * Tests that error() produces a response with error and null result.
   */
  public function testErrorProducesResponseWithErrorAndNoResult(): void {
    $response = McpResponse::error('1', -32601, 'Method not found.');

    $this->assertNotNull($response->error);
    $this->assertNull($response->result);
  }

  /**
   * Tests that error().toArray() contains error key and no result key.
   */
  public function testErrorToArrayContainsErrorKeyAndNoResultKey(): void {
    $response = McpResponse::error('1', -32601, 'Method not found.');
    $array = $response->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that error().toArray() contains the correct error code and message.
   */
  public function testErrorToArrayContainsCorrectErrorCode(): void {
    $response = McpResponse::error('1', -32600, 'Invalid request.');
    $array = $response->toArray();

    $this->assertSame(-32600, $array['error']['code']);
    $this->assertSame('Invalid request.', $array['error']['message']);
  }

  /**
   * Tests that toArray() always contains the jsonrpc key.
   */
  public function testToArrayAlwaysContainsJsonrpcKey(): void {
    $response = McpResponse::success(NULL, []);
    $array = $response->toArray();

    $this->assertArrayHasKey('jsonrpc', $array);
    $this->assertSame('2.0', $array['jsonrpc']);
  }

  /**
   * Tests that toArray() always contains the id key, even when null.
   */
  public function testToArrayAlwaysContainsIdKeyEvenWhenNull(): void {
    $response = McpResponse::success(NULL, []);
    $array = $response->toArray();

    $this->assertArrayHasKey('id', $array);
    $this->assertNull($array['id']);
  }

  /**
   * Tests that toArray() correctly mirrors the request correlation id.
   */
  public function testToArrayCorrelatesIdFromRequest(): void {
    $response = McpResponse::success('my-id', []);
    $array = $response->toArray();

    $this->assertSame('my-id', $array['id']);
  }

  /**
   * Tests that error().toArray() does not include data when data is null.
   */
  public function testErrorToArrayDoesNotIncludeDataWhenNull(): void {
    $response = McpResponse::error('1', -32603, 'Internal error.');
    $array = $response->toArray();

    $this->assertArrayNotHasKey('data', $array['error']);
  }

  /**
   * Tests that error().toArray() includes data when data is provided.
   */
  public function testErrorToArrayIncludesDataWhenProvided(): void {
    $response = McpResponse::error('1', -32602, 'Invalid params.', ['field' => 'machine_name']);
    $array = $response->toArray();

    $this->assertArrayHasKey('data', $array['error']);
    $this->assertSame(['field' => 'machine_name'], $array['error']['data']);
  }

}
