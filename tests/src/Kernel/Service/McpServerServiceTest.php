<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\drupilot\Service\McpServerService;
use Drupal\drupilot\Service\ToolRegistryService;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\drupilot\ValueObject\McpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests McpServerService protocol routing and error handling.
 *
 * This test lives in the Kernel suite because McpToolPluginManager is a final
 * class that cannot be mocked in pure unit tests, requiring real DI wiring.
 */
#[CoversClass(McpServerService::class)]
#[Group('drupilot')]
#[RunTestsInSeparateProcesses]
final class McpServerServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<int, string>
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'field_ui',
    'text',
    'taxonomy',
    'media',
    'file',
    'image',
    'drupilot',
  ];

  /**
   * The service under test.
   */
  private McpServerService $server;

  /**
   * The tool registry.
   */
  private ToolRegistryService $registry;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['drupilot']);

    /** @var \Drupal\drupilot\Service\McpServerService $server */
    $server = $this->container->get('drupilot.server');
    $this->server = $server;

    /** @var \Drupal\drupilot\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupilot.tool_registry');
    $this->registry = $registry;
  }

  /**
   * Builds a valid McpRequest for the given method and optional tool params.
   *
   * @param string $method
   *   JSON-RPC method name.
   * @param string $toolName
   *   Tool name for tools/call; empty string to omit params.
   * @param array<string, mixed> $arguments
   *   Arguments for the tool.
   */
  private function buildRequest(string $method, string $toolName = '', array $arguments = []): McpRequest {
    $data = [
      'jsonrpc' => '2.0',
      'id' => '1',
      'method' => $method,
    ];
    if ($toolName !== '') {
      $data['params'] = [
        'name' => $toolName,
        'arguments' => $arguments,
      ];
    }

    return McpRequest::fromArray($data);
  }

  /**
   * Tests that tools/list returns a success response with a tools key.
   */
  public function testHandleToolsListReturnsSuccessResponseWithToolsKey(): void {
    $request = $this->buildRequest('tools/list');
    $response = $this->server->handle($request);
    $array = $response->toArray();

    $this->assertArrayHasKey('result', $array);
    $this->assertArrayHasKey('tools', $array['result']);
  }

  /**
   * Tests that tools/list returns only enabled tools.
   */
  public function testHandleToolsListReturnsOnlyEnabledTools(): void {
    $this->registry->enableTool('content_type_create');

    $request = $this->buildRequest('tools/list');
    $response = $this->server->handle($request);
    $array = $response->toArray();

    $toolNames = array_column($array['result']['tools'], 'name');
    $this->assertContains('content_type_create', $toolNames);
  }

  /**
   * Tests that tools/list returns an empty tools array when no tools are enabled.
   */
  public function testHandleToolsListReturnsEmptyToolsArrayWhenNoneEnabled(): void {
    $request = $this->buildRequest('tools/list');
    $response = $this->server->handle($request);
    $array = $response->toArray();

    $this->assertSame([], $array['result']['tools']);
  }

  /**
   * Tests that an unknown method returns the METHOD_NOT_FOUND error code.
   */
  public function testHandleUnknownMethodReturnsMethodNotFoundError(): void {
    $request = $this->buildRequest('unknown/method');
    $response = $this->server->handle($request);
    $array = $response->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::METHOD_NOT_FOUND, $array['error']['code']);
  }

  /**
   * Tests that tools/call on a disabled tool returns the TOOL_DISABLED error code.
   */
  public function testHandleToolsCallWithDisabledToolReturnsToolDisabledError(): void {
    // Tool is NOT enabled — default state.
    $request = $this->buildRequest('tools/call', 'content_type_create');
    $response = $this->server->handle($request);
    $array = $response->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::TOOL_DISABLED, $array['error']['code']);
  }

  /**
   * Tests that tools/call on a non-existent (but enabled) tool returns TOOL_NOT_FOUND.
   */
  public function testHandleToolsCallWithNonExistentToolReturnsToolNotFoundError(): void {
    // Enable a non-existent tool id so it passes the "is disabled" check.
    $this->registry->enableTool('completely_nonexistent_tool_xyz');

    $request = $this->buildRequest('tools/call', 'completely_nonexistent_tool_xyz');
    $response = $this->server->handle($request);
    $array = $response->toArray();

    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::TOOL_NOT_FOUND, $array['error']['code']);
  }

  /**
   * Tests that the response always contains the correlation id from the request.
   */
  public function testHandleResponseContainsCorrelationId(): void {
    $request = $this->buildRequest('tools/list');
    $response = $this->server->handle($request);
    $array = $response->toArray();

    $this->assertSame('1', $array['id']);
  }

  /**
   * Tests that handle() never throws, even on unknown methods.
   */
  public function testHandleNeverThrowsOnAnyInput(): void {
    $request = McpRequest::fromArray([
      'jsonrpc' => '2.0',
      'method' => 'totally/unknown',
    ]);

    $response = $this->server->handle($request);

    // The response is always a valid JSON-RPC envelope with at least jsonrpc key.
    $this->assertArrayHasKey('jsonrpc', $response->toArray());
  }

}
