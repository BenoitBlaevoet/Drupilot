<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot\Unit\ValueObject;

use Drupal\Tests\UnitTestCase;
use Drupal\drupilot\ValueObject\McpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests McpRequest::fromArray() construction and property mapping.
 */
#[CoversClass(McpRequest::class)]
#[Group('drupilot')]
final class McpRequestTest extends UnitTestCase {

  /**
   * Tests that fromArray() correctly constructs a valid request with all fields.
   */
  public function testFromArrayConstructsValidRequestWithAllFields(): void {
    $data = [
      'jsonrpc' => '2.0',
      'id' => '42',
      'method' => 'tools/call',
      'params' => [
        'name' => 'content_type_create',
        'arguments' => ['machine_name' => 'article', 'label' => 'Article'],
      ],
    ];

    $request = McpRequest::fromArray($data);

    $this->assertSame('2.0', $request->jsonrpc);
    $this->assertSame('42', $request->id);
    $this->assertSame('tools/call', $request->method);
    $this->assertSame('content_type_create', $request->toolName);
    $this->assertSame(['machine_name' => 'article', 'label' => 'Article'], $request->arguments);
  }

  /**
   * Tests that fromArray() throws when jsonrpc is not "2.0".
   */
  public function testFromArrayThrowsWhenJsonrpcIsNot20(): void {
    $this->expectException(\InvalidArgumentException::class);

    McpRequest::fromArray([
      'jsonrpc' => '1.0',
      'method' => 'tools/list',
    ]);
  }

  /**
   * Tests that fromArray() throws when jsonrpc field is missing.
   */
  public function testFromArrayThrowsWhenJsonrpcIsMissing(): void {
    $this->expectException(\InvalidArgumentException::class);

    McpRequest::fromArray([
      'method' => 'tools/list',
    ]);
  }

  /**
   * Tests that fromArray() throws when method field is missing.
   */
  public function testFromArrayThrowsWhenMethodIsMissing(): void {
    $this->expectException(\InvalidArgumentException::class);

    McpRequest::fromArray([
      'jsonrpc' => '2.0',
    ]);
  }

  /**
   * Tests that fromArray() throws when method is an empty string.
   */
  public function testFromArrayThrowsWhenMethodIsEmptyString(): void {
    $this->expectException(\InvalidArgumentException::class);

    McpRequest::fromArray([
      'jsonrpc' => '2.0',
      'method' => '',
    ]);
  }

  /**
   * Tests that toolName defaults to empty string when params.name is absent.
   */
  public function testFromArrayToolNameDefaultsToEmptyStringWhenParamsNameAbsent(): void {
    $request = McpRequest::fromArray([
      'jsonrpc' => '2.0',
      'method' => 'tools/list',
    ]);

    $this->assertSame('', $request->toolName);
  }

  /**
   * Tests that arguments defaults to empty array when params.arguments is absent.
   */
  public function testFromArrayArgumentsDefaultToEmptyArrayWhenAbsent(): void {
    $request = McpRequest::fromArray([
      'jsonrpc' => '2.0',
      'method' => 'tools/list',
    ]);

    $this->assertSame([], $request->arguments);
  }

  /**
   * Tests that fromArray() accepts an integer id.
   */
  public function testFromArrayAcceptsIntegerId(): void {
    $request = McpRequest::fromArray([
      'jsonrpc' => '2.0',
      'id' => 1,
      'method' => 'tools/list',
    ]);

    $this->assertSame(1, $request->id);
  }

  /**
   * Tests that fromArray() results in null id when id is absent.
   */
  public function testFromArrayAcceptsNullId(): void {
    $request = McpRequest::fromArray([
      'jsonrpc' => '2.0',
      'method' => 'tools/list',
    ]);

    $this->assertNull($request->id);
  }

  /**
   * Tests that toolName is correctly extracted from params.name.
   */
  public function testFromArrayExtractsToolNameFromParamsName(): void {
    $request = McpRequest::fromArray([
      'jsonrpc' => '2.0',
      'method' => 'tools/call',
      'params' => ['name' => 'node_create'],
    ]);

    $this->assertSame('node_create', $request->toolName);
  }

}
