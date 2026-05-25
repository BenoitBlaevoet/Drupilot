<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the MCP HTTP endpoint (POST /mcp/v1).
 */
#[Group('drupilot')]
#[RunTestsInSeparateProcesses]
final class McpEndpointTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Bearer token used across tests.
   */
  private const string TOKEN = 'test-mcp-bearer-token-abc123';

  /**
   * MCP endpoint path.
   */
  private const string ENDPOINT = '/mcp/v1';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Store bearer token in config.
    $this->config('drupilot.settings')
      ->set('bearer_token', self::TOKEN)
      ->save();
  }

  /**
   * Sends a POST to /mcp/v1 and returns status code and raw response body.
   *
   * @param string|null $authHeader
   *   Authorization header value, or null to omit.
   * @param string $body
   *   Raw request body.
   *
   * @return array{status: int, body: string}
   *   Associative array with HTTP status code and raw response body.
   */
  private function postRaw(?string $authHeader, string $body): array {
    $options = [
      'body' => $body,
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ];

    if ($authHeader !== NULL) {
      $options['headers']['Authorization'] = $authHeader;
    }

    $client = $this->getHttpClient();
    $response = $client->request('POST', $this->baseUrl . self::ENDPOINT, $options);

    return [
      'status' => $response->getStatusCode(),
      'body' => (string) $response->getBody(),
    ];
  }

  /**
   * Sends a POST and returns status + decoded JSON body.
   *
   * Only use when the response is expected to be valid JSON (HTTP 200 responses
   * from the MCP endpoint).
   *
   * @param string|null $authHeader
   *   Authorization header value, or null to omit.
   * @param string $body
   *   Raw request body.
   *
   * @return array{status: int, data: array<string, mixed>}
   *   Associative array with HTTP status code and decoded JSON data.
   */
  private function postJson(?string $authHeader, string $body): array {
    $raw = $this->postRaw($authHeader, $body);
    /** @var array<string, mixed> $data */
    $data = json_decode($raw['body'], TRUE, 512, JSON_THROW_ON_ERROR);
    return [
      'status' => $raw['status'],
      'data' => $data,
    ];
  }

  /**
   * Returns a valid JSON-RPC body for tools/list.
   */
  private function toolsListBody(): string {
    return json_encode([
      'jsonrpc' => '2.0',
      'id' => '1',
      'method' => 'tools/list',
    ], JSON_THROW_ON_ERROR);
  }

  /**
   * Returns a valid JSON-RPC body for tools/call.
   *
   * @param string $toolName
   *   The tool name.
   * @param array<string, mixed> $arguments
   *   The tool arguments.
   */
  private function toolsCallBody(string $toolName, array $arguments = []): string {
    return json_encode([
      'jsonrpc' => '2.0',
      'id' => '1',
      'method' => 'tools/call',
      'params' => [
        'name' => $toolName,
        'arguments' => $arguments,
      ],
    ], JSON_THROW_ON_ERROR);
  }

  /**
   * Tests that a POST without Authorization header is rejected with HTTP 403.
   */
  public function testUnauthenticatedPostReturnsForbidden(): void {
    $result = $this->postRaw(NULL, $this->toolsListBody());

    $this->assertSame(403, $result['status']);
  }

  /**
   * Tests that a POST with an incorrect token is rejected with HTTP 403.
   */
  public function testWrongTokenReturnsForbidden(): void {
    $result = $this->postRaw('Bearer wrong-token', $this->toolsListBody());

    $this->assertSame(403, $result['status']);
  }

  /**
   * Tests that a valid token with tools/list returns HTTP 200 with JSON-RPC envelope.
   */
  public function testValidTokenToolsListReturnsOkWithJsonRpcEnvelope(): void {
    $result = $this->postJson('Bearer ' . self::TOKEN, $this->toolsListBody());

    $this->assertSame(200, $result['status']);
    $this->assertArrayHasKey('jsonrpc', $result['data']);
    $this->assertSame('2.0', $result['data']['jsonrpc']);
    $this->assertArrayHasKey('result', $result['data']);
    $this->assertArrayHasKey('tools', $result['data']['result']);
  }

  /**
   * Tests that invalid JSON body returns HTTP 200 with parse error code -32700.
   */
  public function testInvalidJsonBodyReturnsParseError(): void {
    $result = $this->postJson('Bearer ' . self::TOKEN, '{invalid json}');

    $this->assertSame(200, $result['status']);
    $this->assertArrayHasKey('error', $result['data']);
    $this->assertSame(-32700, $result['data']['error']['code']);
  }

  /**
   * Tests that an unknown method returns the JSON-RPC method-not-found error -32601.
   */
  public function testUnknownMethodReturnsMethodNotFoundError(): void {
    $body = json_encode([
      'jsonrpc' => '2.0',
      'id' => '1',
      'method' => 'some/unknown/method',
    ], JSON_THROW_ON_ERROR);

    $result = $this->postJson('Bearer ' . self::TOKEN, $body);

    $this->assertSame(200, $result['status']);
    $this->assertArrayHasKey('error', $result['data']);
    $this->assertSame(-32601, $result['data']['error']['code']);
  }

  /**
   * Tests that calling a disabled tool returns the TOOL_DISABLED error code -32000.
   */
  public function testToolsCallWithDisabledToolReturnsToolDisabledError(): void {
    // content_type_create is disabled by default.
    $result = $this->postJson(
      'Bearer ' . self::TOKEN,
      $this->toolsCallBody('content_type_create', ['machine_name' => 'test_ct', 'label' => 'Test']),
    );

    $this->assertSame(200, $result['status']);
    $this->assertArrayHasKey('error', $result['data']);
    $this->assertSame(-32000, $result['data']['error']['code']);
  }

  /**
   * Tests that enabling a tool makes it appear in the tools/list response.
   */
  public function testEnabledToolAppearsInToolsList(): void {
    /** @var \Drupal\drupilot\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupilot.tool_registry');
    $registry->enableTool('content_type_create');

    $result = $this->postJson('Bearer ' . self::TOKEN, $this->toolsListBody());

    $this->assertSame(200, $result['status']);
    $toolNames = array_column($result['data']['result']['tools'], 'name');
    $this->assertContains('content_type_create', $toolNames);
  }

  /**
   * Tests that tools/call on an enabled content_type_create tool creates a content type.
   */
  public function testToolsCallContentTypeCreateCreatesContentType(): void {
    /** @var \Drupal\drupilot\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupilot.tool_registry');
    $registry->enableTool('content_type_create');

    $result = $this->postJson(
      'Bearer ' . self::TOKEN,
      $this->toolsCallBody('content_type_create', [
        'machine_name' => 'mcp_test_article',
        'label' => 'MCP Test Article',
      ]),
    );

    $this->assertSame(200, $result['status']);
    $this->assertArrayHasKey('result', $result['data']);
    $this->assertSame('mcp_test_article', $result['data']['result']['machine_name']);

    // Verify the content type was actually created.
    /** @var \Drupal\node\NodeTypeInterface|null $nodeType */
    $nodeType = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->load('mcp_test_article');
    $this->assertNotNull($nodeType);
  }

  /**
   * Tests that tools/call on content_type_delete removes an existing content type.
   */
  public function testToolsCallContentTypeDeleteDeletesContentType(): void {
    /** @var \Drupal\drupilot\Service\ToolRegistryService $registry */
    $registry = $this->container->get('drupilot.tool_registry');
    $registry->enableTool('content_type_create');
    $registry->enableTool('content_type_delete');

    // First create.
    $this->postJson(
      'Bearer ' . self::TOKEN,
      $this->toolsCallBody('content_type_create', [
        'machine_name' => 'mcp_del_test',
        'label' => 'MCP Delete Test',
      ]),
    );

    // Then delete.
    $result = $this->postJson(
      'Bearer ' . self::TOKEN,
      $this->toolsCallBody('content_type_delete', [
        'machine_name' => 'mcp_del_test',
      ]),
    );

    $this->assertSame(200, $result['status']);
    $this->assertArrayHasKey('result', $result['data']);
    $this->assertTrue($result['data']['result']['deleted']);

    // Verify the content type no longer exists.
    /** @var \Drupal\node\NodeTypeInterface|null $nodeType */
    $nodeType = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->load('mcp_del_test');
    $this->assertNull($nodeType);
  }

}
