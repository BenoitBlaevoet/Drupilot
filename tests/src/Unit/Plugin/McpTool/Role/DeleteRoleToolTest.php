<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_mcp\Unit\Plugin\McpTool\Role;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\drupal_mcp\Plugin\McpTool\Role\DeleteRoleTool;
use Drupal\drupal_mcp\ValueObject\McpError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the security guards in DeleteRoleTool.
 */
#[CoversClass(DeleteRoleTool::class)]
#[Group('drupal_mcp')]
final class DeleteRoleToolTest extends UnitTestCase {

  /**
   * Builds a DeleteRoleTool with the given storage mock.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The user_role entity storage mock.
   *
   * @return \Drupal\drupal_mcp\Plugin\McpTool\Role\DeleteRoleTool
   *   The tool under test.
   */
  private function buildTool(EntityStorageInterface $storage): DeleteRoleTool {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('user_role')
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);

    return new DeleteRoleTool(
      $entityTypeManager,
      $logger,
    );
  }

  /**
   * Returns a storage mock that must never have load() called.
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
   * Provides the three protected system role machine names.
   *
   * @return array<string, array{string}>
   *   Keyed by description.
   */
  public static function protectedRoleProvider(): array {
    return [
      'anonymous role'     => ['anonymous'],
      'authenticated role' => ['authenticated'],
      'administrator role' => ['administrator'],
    ];
  }

  /**
   * Tests that protected system roles cannot be deleted.
   *
   * @param string $machineName
   *   The protected role machine name.
   */
  #[DataProvider('protectedRoleProvider')]
  public function testExecuteRejectsProtectedSystemRole(string $machineName): void {
    $tool = $this->buildTool($this->unusedStorage());

    $response = $tool->execute(['machine_name' => $machineName]);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
    $this->assertArrayNotHasKey('result', $array);
  }

  /**
   * Tests that a non-existent custom role returns an error.
   */
  public function testExecuteReturnsErrorWhenRoleNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('custom_role')->willReturn(NULL);

    $tool = $this->buildTool($storage);

    $response = $tool->execute(['machine_name' => 'custom_role']);

    $array = $response->toArray();
    $this->assertArrayHasKey('error', $array);
    $this->assertSame(McpError::INVALID_PARAMS, $array['error']['code']);
  }

  /**
   * Tests the happy path: an existing custom role has delete() called on it.
   */
  public function testExecuteCallsDeleteOnExistingRole(): void {
    $role = $this->createMock(ConfigEntityInterface::class);
    $role->expects($this->once())->method('delete');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('custom_role')->willReturn($role);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('user_role')
      ->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->method('info');

    $tool = new DeleteRoleTool(
      $entityTypeManager,
      $logger,
    );

    $response = $tool->execute(['machine_name' => 'custom_role']);

    $array = $response->toArray();
    $this->assertArrayNotHasKey('error', $array);
    $this->assertArrayHasKey('result', $array);
    $this->assertSame('custom_role', $array['result']['machine_name']);
    $this->assertTrue($array['result']['deleted']);
  }

}
