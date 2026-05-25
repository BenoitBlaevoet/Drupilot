<?php

declare(strict_types=1);

namespace Drupal\drupal_mcp\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates the bearer token on incoming MCP requests.
 */
final class McpTokenAccessCheck implements AccessInterface {

  /**
   * Constructs the access checker.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal configuration factory.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Resolves access for an MCP endpoint request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Allowed if the bearer token matches the configured token; forbidden
   *   otherwise. Always uncacheable.
   */
  public function access(Request $request): AccessResultInterface {
    $configured = $this->configFactory->get('drupal_mcp.settings')->get('bearer_token');
    if (!is_string($configured) || $configured === '') {
      return AccessResult::forbidden('MCP bearer token is not configured.')->setCacheMaxAge(0);
    }

    $header = $request->headers->get('Authorization');
    if (!is_string($header) || $header === '') {
      return AccessResult::forbidden('Missing Authorization header.')->setCacheMaxAge(0);
    }

    if (stripos($header, 'Bearer ') !== 0) {
      return AccessResult::forbidden('Malformed Authorization header.')->setCacheMaxAge(0);
    }

    $provided = trim(substr($header, 7));
    if ($provided === '') {
      return AccessResult::forbidden('Empty bearer token.')->setCacheMaxAge(0);
    }

    if (!hash_equals($configured, $provided)) {
      return AccessResult::forbidden('Invalid bearer token.')->setCacheMaxAge(0);
    }

    return AccessResult::allowed()->setCacheMaxAge(0);
  }

}
