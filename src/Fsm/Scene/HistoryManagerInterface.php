<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

/**
 * Manages the scene history stack for back-navigation.
 *
 * Provides the forward declaration used by `SceneWizard`; the concrete
 * `HistoryManager` (Task 5.11) implements this interface.
 *
 * Mirrors the history-related helpers on `ScenesManager`
 * (`aiogram/fsm/scene.py:524-540`).
 */
interface HistoryManagerInterface
{
  /**
   * Remove all entries from the history stack.
   *
   * Called by `SceneWizard::exit()` before dispatching the exit action.
   */
  public function clear(): void;

  /**
   * Record the current scene in the history stack.
   *
   * Called by `SceneWizard::leave()` when `$withHistory` is `true`.
   */
  public function snapshot(): void;

  /**
   * Pop the last entry from the history stack and return it.
   *
   * Called by `SceneWizard::back()` to determine which scene to re-enter.
   *
   * @return null|string The class-string or state string of the previous
   *                     scene, or `null` when the stack is empty.
   */
  public function rollback(): ?string;
}
