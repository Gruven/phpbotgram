<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\SceneAction;
use Gruven\PhpBotGram\Fsm\State;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Upstream `tests/test_fsm/test_scene.py::TestAfter` cases deliberately
 * not ported:
 *
 * - No deliberate skips. All three `TestAfter` cases (`test_exit`, `test_back`,
 *   `test_goto`) are ported in this file.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class AfterTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // After::exit()
  // ------------------------------------------------------------------ //

  /**
   * `After::exit()` produces an `After` with `Exit` action and `null` scene.
   */
  public function testExitFactoryProducesExitAction(): void
  {
    $after = After::exit();

    self::assertSame(SceneAction::Exit, $after->action);
    self::assertNull($after->scene);
  }

  // ------------------------------------------------------------------ //
  // After::back()
  // ------------------------------------------------------------------ //

  /**
   * `After::back()` produces an `After` with `Back` action and `null` scene.
   */
  public function testBackFactoryProducesBackAction(): void
  {
    $after = After::back();

    self::assertSame(SceneAction::Back, $after->action);
    self::assertNull($after->scene);
  }

  // ------------------------------------------------------------------ //
  // After::goto()
  // ------------------------------------------------------------------ //

  /**
   * `After::goto(string)` produces an `After` with `Enter` action and the
   * given scene string.
   */
  public function testGotoFactoryWithStringScene(): void
  {
    $after = After::goto('my_scene');

    self::assertSame(SceneAction::Enter, $after->action);
    self::assertSame('my_scene', $after->scene);
  }

  /**
   * `After::goto(State)` accepts a `State` instance as the scene.
   */
  public function testGotoFactoryWithStateScene(): void
  {
    $state = new State(state: 'idle', groupName: 'Flow');
    $after = After::goto($state);

    self::assertSame(SceneAction::Enter, $after->action);
    self::assertSame($state, $after->scene);
  }

  /**
   * `After::goto(null)` produces an `Enter` action with `null` scene (clear
   * FSM state).
   */
  public function testGotoFactoryWithNullScene(): void
  {
    $after = After::goto(null);

    self::assertSame(SceneAction::Enter, $after->action);
    self::assertNull($after->scene);
  }

  // ------------------------------------------------------------------ //
  // Direct construction
  // ------------------------------------------------------------------ //

  /**
   * Direct construction with explicit action and scene is valid.
   */
  public function testDirectConstructionWithExplicitAction(): void
  {
    $after = new After(SceneAction::Leave);

    self::assertSame(SceneAction::Leave, $after->action);
    self::assertNull($after->scene);
  }

  /**
   * `After` is `readonly` — properties cannot be mutated after construction.
   */
  public function testAfterIsReadonly(): void
  {
    $after = After::exit();

    $ref = new ReflectionClass($after);

    self::assertTrue($ref->isReadOnly());
  }
}
