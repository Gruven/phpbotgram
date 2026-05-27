<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

/**
 * Enumerates the lifecycle actions a scene handler can perform.
 *
 * Mirrors `SceneAction` (`aiogram/fsm/scene.py:167-171`).
 *
 * Case mapping (upstream → PHP):
 *   - `enter` → `Enter`  — transition into a new scene.
 *   - `leave` → `Leave`  — leave the current scene without exiting FSM.
 *   - `exit`  → `Exit`   — exit the FSM entirely (clear state + history).
 *   - `back`  → `Back`   — roll back to the previous scene in history.
 */
enum SceneAction
{
  /**
   * Transition into a (possibly new) scene.
   *
   * Mirrors `SceneAction.enter`.
   */
  case Enter;

  /**
   * Leave the current scene without clearing FSM state.
   *
   * Mirrors `SceneAction.leave`.
   */
  case Leave;

  /**
   * Exit the FSM entirely (clear state and scene history).
   *
   * Mirrors `SceneAction.exit`. Named `Exit` — `exit` is a language
   * construct in PHP but is a valid identifier in enum-case position.
   */
  case Exit;

  /**
   * Roll back to the previous scene recorded in the history stack.
   *
   * Mirrors `SceneAction.back`.
   */
  case Back;
}
