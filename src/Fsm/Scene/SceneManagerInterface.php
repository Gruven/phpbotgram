<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\State;

/**
 * Controls scene transitions on behalf of `SceneWizard`.
 *
 * Provides the forward declaration used by `SceneWizard`; the concrete
 * `ScenesManager` (Task 5.10) implements this interface.
 *
 * Mirrors the relevant surface of `ScenesManager`
 * (`aiogram/fsm/scene.py:567-655`).
 */
interface SceneManagerInterface
{
  /**
   * Access the history manager for this scene session.
   */
  public function history(): HistoryManagerInterface;

  /**
   * Transition to the given scene (or clear FSM state when `null`).
   *
   * @param null|class-string<Scene>|State|string $scene Target scene class
   *                                                     name, a `State` instance, a bare state string, or `null` to clear
   *                                                     FSM state entirely.
   * @param bool $checkActive When `false` the manager skips the "is the
   *                          scene already active?" guard. Used internally
   *                          by `SceneWizard` for chained transitions.
   * @param mixed ...$kwargs Additional context forwarded to the scene's
   *                         lifecycle methods.
   */
  public function enter(
    null|State|string $scene,
    bool $checkActive = true,
    mixed ...$kwargs,
  ): void;
}
