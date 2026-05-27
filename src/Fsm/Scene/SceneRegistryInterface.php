<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

use Gruven\PhpBotGram\Exceptions\SceneException;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\State;

/**
 * Resolves a scene identifier to a concrete `Scene` subclass name.
 *
 * Provides the forward declaration used by `ScenesManager`; the concrete
 * `SceneRegistry` (Task 5.11) implements this interface.
 *
 * Mirrors the resolution logic inside `ScenesManager._get_scene`
 * (`aiogram/fsm/scene.py:670-671`).
 */
interface SceneRegistryInterface
{
  /**
   * Resolve `$sceneType` to a concrete `Scene` class-string.
   *
   * Accepted input forms:
   * - A `class-string<Scene>` — returned as-is after validating the class is
   *   registered.
   * - A `State` instance — resolved by matching `$state->state()` against
   *   the registered scene states.
   * - A bare state `string` — resolved by matching against the registered
   *   scene states.
   * - `null` — always throws `SceneException` (used by `ScenesManager` to
   *   detect the "clear FSM state" path).
   *
   * @param null|class-string<Scene>|State|string $sceneType The identifier to
   *                                                         resolve.
   *
   * @return class-string<Scene> The resolved concrete scene class.
   *
   * @throws SceneException When `$sceneType` is `null` or does not match any
   *                        registered scene.
   */
  public function get(State|string|null $sceneType): string;
}
