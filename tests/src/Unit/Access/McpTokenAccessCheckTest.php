<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot\Unit\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\drupilot\Access\McpTokenAccessCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests McpTokenAccessCheck bearer-token validation.
 */
#[CoversClass(McpTokenAccessCheck::class)]
#[Group('drupilot')]
final class McpTokenAccessCheckTest extends UnitTestCase {

  private const string VALID_TOKEN = 'super-secret-bearer-token';

  /**
   * Builds an McpTokenAccessCheck with a given configured bearer token.
   *
   * @param string|null $configuredToken
   *   The token stored in config, or null for unconfigured.
   */
  private function buildAccessChecker(?string $configuredToken): McpTokenAccessCheck {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('bearer_token')
      ->willReturn($configuredToken);

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('drupilot.settings')
      ->willReturn($config);

    return new McpTokenAccessCheck($factory);
  }

  /**
   * Builds a Request object with an optional Authorization header value.
   *
   * @param string|null $authorizationHeader
   *   The Authorization header value, or null to omit the header.
   */
  private function buildRequest(?string $authorizationHeader): Request {
    $request = Request::create('/mcp/v1', 'POST');
    if ($authorizationHeader !== NULL) {
      $request->headers->set('Authorization', $authorizationHeader);
    }
    return $request;
  }

  /**
   * Tests that access is allowed when the correct bearer token is provided.
   */
  public function testAccessAllowedWhenCorrectBearerTokenProvided(): void {
    $checker = $this->buildAccessChecker(self::VALID_TOKEN);
    $request = $this->buildRequest('Bearer ' . self::VALID_TOKEN);

    $result = $checker->access($request);

    $this->assertInstanceOf(AccessResultAllowed::class, $result);
  }

  /**
   * Tests that access is forbidden when the Authorization header is absent.
   */
  public function testAccessForbiddenWhenAuthorizationHeaderIsAbsent(): void {
    $checker = $this->buildAccessChecker(self::VALID_TOKEN);
    $request = $this->buildRequest(NULL);

    $result = $checker->access($request);

    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that access is forbidden when the provided token does not match.
   */
  public function testAccessForbiddenWhenTokenDoesNotMatch(): void {
    $checker = $this->buildAccessChecker(self::VALID_TOKEN);
    $request = $this->buildRequest('Bearer wrong-token');

    $result = $checker->access($request);

    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that access is forbidden when the configured token is an empty string.
   */
  public function testAccessForbiddenWhenConfiguredTokenIsEmpty(): void {
    $checker = $this->buildAccessChecker('');
    $request = $this->buildRequest('Bearer ' . self::VALID_TOKEN);

    $result = $checker->access($request);

    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that access is forbidden when the configured token is null.
   */
  public function testAccessForbiddenWhenConfiguredTokenIsNull(): void {
    $checker = $this->buildAccessChecker(NULL);
    $request = $this->buildRequest('Bearer ' . self::VALID_TOKEN);

    $result = $checker->access($request);

    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that access is forbidden when the Authorization header lacks the Bearer prefix.
   */
  public function testAccessForbiddenWhenAuthorizationHeaderLacksBearerPrefix(): void {
    $checker = $this->buildAccessChecker(self::VALID_TOKEN);
    $request = $this->buildRequest(self::VALID_TOKEN);

    $result = $checker->access($request);

    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that access is forbidden when the Bearer token is an empty string.
   */
  public function testAccessForbiddenWhenBearerTokenIsEmpty(): void {
    $checker = $this->buildAccessChecker(self::VALID_TOKEN);
    $request = $this->buildRequest('Bearer ');

    $result = $checker->access($request);

    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that the access result is always uncacheable (maxAge = 0).
   */
  public function testAccessResultIsUncacheable(): void {
    $checker = $this->buildAccessChecker(self::VALID_TOKEN);
    $request = $this->buildRequest('Bearer ' . self::VALID_TOKEN);

    $result = $checker->access($request);

    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertSame(0, $result->getCacheMaxAge());
  }

  /**
   * Tests correct token is allowed and wrong token is forbidden, behaviorally.
   *
   * This verifies hash_equals-compatible semantics: the same checker with
   * the same configuration correctly distinguishes correct vs. incorrect tokens.
   */
  public function testCorrectTokenAllowedAndWrongTokenForbiddenBehaviorally(): void {
    $checker = $this->buildAccessChecker(self::VALID_TOKEN);

    $allowedResult = $checker->access($this->buildRequest('Bearer ' . self::VALID_TOKEN));
    $forbiddenResult = $checker->access($this->buildRequest('Bearer different-token'));

    $this->assertInstanceOf(AccessResultAllowed::class, $allowedResult);
    $this->assertInstanceOf(AccessResultForbidden::class, $forbiddenResult);
  }

}
