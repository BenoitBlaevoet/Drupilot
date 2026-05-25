<?php

declare(strict_types=1);

namespace Drupal\Tests\drupilot\Unit\Plugin\McpTool\User;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupilot\Plugin\McpTool\User\DeleteUserTool;
use Drupal\drupilot\ValueObject\McpError;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the security guards in DeleteUserTool.
 */
#[CoversClass(DeleteUserTool::class)]
#[Group('drupilot')]
final class DeleteUserToolTest extends UnitTestCase {

  /**
   * Builds a DeleteUserTool with the given storage mock.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The user entity storage mock.
   *
   * @return \Drupal\drupilot\Plugin\McpTool\User\DeleteUserTool
   *   The tool under test.
   */
  private function buildTool(EntityStorageInterface $storage): DeleteUserTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('user')
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new DeleteUserTool(
      $entityTypeManager,
      $logger,
    );
  }

  /**
   * Returns a storage mock that will never be asked to load an entity.
   *
   * Used for guard-clause tests that short-circuit before the storage call.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The storage mock.
   */
  private function unusedStorage(): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->never())->method('load');
    return $storage;
  }

  /**
   * Tests that uid=1 (superadmin) is rejected with a descriptive error.
   */
  public function testExecuteRejectsUid1WithSuperadminMessage(): void {
    $tool = $this->buildTool($this->unusedStorage());

    $response = $tool->execute(['uid' => 1]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertStringContainsStringIgnoringCase('superadmin', $array['error']['message']);
  }

  /**
   * Tests that uid=0 is rejected as an invalid user id.
   */
  public function testExecuteRejectsUid0AsInvalid(): void {
    $tool = $this->buildTool($this->unusedStorage());

    $response = $tool->execute(['uid' => 0]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that a uid with no matching user entity returns an error.
   */
  public function testExecuteReturnsErrorWhenUserNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(99)->willReturn(NULL);

    $tool = $this->buildTool($storage);

    $response = $tool->execute(['uid' => 99]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
  }

  /**
   * Tests the happy path: an existing user has delete() called on it.
   */
  public function testExecuteCallsDeleteOnExistingUser(): void {
    $user = $this->createMock(UserInterface::class);
    // The 'block' cancel method calls set() and save(), not delete().
    // Use cancel_method=delete so the fallback path calls $user->delete().
    $user->expects($this->once())->method('delete');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(5)->willReturn($user);

    // Suppress logger->info() call — not relevant to this assertion.
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('user')
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->method('info');

    $tool = new DeleteUserTool(
      $entityTypeManager,
      $logger,
    );

    // Use cancel_method=delete without user_cancel() function available (no
    // Drupal bootstrap) so the code falls through to $user->delete().
    $response = $tool->execute(['uid' => 5, 'cancel_method' => 'delete']);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
  }

}
