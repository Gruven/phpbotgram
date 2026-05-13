<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\SceneAction;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;

/**
 * Upstream `tests/test_fsm/test_scene.py` `SceneAction` cases deliberately
 * not ported here:
 *
 * - `SceneAction` is used inline in many upstream tests; direct unit tests
 *   for the enum itself are not separate in the upstream file. All enum cases
 *   are ported in this file.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class SceneActionTest extends TestCase
{
  /**
   * All four expected cases exist and are distinct instances.
   */
  public function testAllCasesExist(): void
  {
    // Non-backed enum — verify by direct identity comparison.
    self::assertInstanceOf(SceneAction::class, SceneAction::Enter);
    self::assertInstanceOf(SceneAction::class, SceneAction::Leave);
    self::assertInstanceOf(SceneAction::class, SceneAction::Exit);
    self::assertInstanceOf(SceneAction::class, SceneAction::Back);

    self::assertNotSame(SceneAction::Enter, SceneAction::Leave);
    self::assertNotSame(SceneAction::Exit, SceneAction::Back);
  }

  /**
   * Cases are returned in declaration order by `cases()`.
   */
  public function testCaseOrdering(): void
  {
    $names = array_map(static fn(SceneAction $c) => $c->name, SceneAction::cases());

    self::assertSame(['Enter', 'Leave', 'Exit', 'Back'], $names);
  }

  /**
   * `SceneAction` is a non-backed (unit) enum — cases have no `value`
   * property on their `ReflectionEnumUnitCase` instances.
   */
  public function testEnumIsNotBacked(): void
  {
    $ref = new ReflectionEnum(SceneAction::class);

    // Non-backed enum: getBackingType() returns null.
    self::assertNull($ref->getBackingType());
  }

  /**
   * Exactly four cases are declared.
   */
  public function testExactlyFourCases(): void
  {
    self::assertCount(4, SceneAction::cases());
  }
}
