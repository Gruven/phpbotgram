<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

/**
 * Describes what the framework should do **after** a scene handler returns.
 *
 * Mirrors the `After` dataclass (`aiogram/fsm/scene.py:890-906`).
 * Upstream uses `@dataclass` with three class-method factories; the PHP port
 * uses a `final readonly` class with static factory methods and named
 * constructor arguments.
 *
 * Usage:
 *
 *   #[OnMessage(after: After::exit())]
 *   public function handleMessage(...): void { ... }
 *
 * Factory methods:
 *
 *   - `After::exit()`          — exit FSM after the handler returns.
 *   - `After::back()`          — go back one step in history.
 *   - `After::goto($scene)`    — transition to `$scene`.
 */
final readonly class After
{
  /**
   * @param SceneAction $action The action to execute after the handler.
   * @param null|class-string<Scene>|State|string $scene Target scene for
   *                                                     `Enter` transitions. Unused (and should be `null`) for `Exit` / `Back`.
   */
  public function __construct(
    public SceneAction $action,
    public null|State|string $scene = null,
  ) {}

  /**
   * Exit the FSM after the handler returns.
   *
   * Mirrors `After.exit()` (`aiogram/fsm/scene.py:894-895`).
   */
  public static function exit(): self
  {
    return new self(action: SceneAction::Exit);
  }

  /**
   * Roll back to the previous scene after the handler returns.
   *
   * Mirrors `After.back()` (`aiogram/fsm/scene.py:897-898`).
   */
  public static function back(): self
  {
    return new self(action: SceneAction::Back);
  }

  /**
   * Transition to `$scene` after the handler returns.
   *
   * Mirrors `After.goto()` (`aiogram/fsm/scene.py:900-901`).
   *
   * @param null|class-string<Scene>|State|string $scene The target scene
   *                                                     class name, a `State` instance, a bare state string, or `null` to
   *                                                     clear FSM state.
   */
  public static function goto(null|State|string $scene): self
  {
    return new self(action: SceneAction::Enter, scene: $scene);
  }
}
