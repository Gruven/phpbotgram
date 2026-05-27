<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

use Gruven\PhpBotGram\Exceptions\SceneException;
use Gruven\PhpBotGram\Fsm\Scene\SceneConfig;
use Gruven\PhpBotGram\Fsm\Scene\SceneManagerInterface;

/**
 * Per-scene state machine instance handed to each scene method.
 *
 * `SceneWizard` is the surface that scene handler methods use to drive
 * transitions, read/write FSM data, and dispatch lifecycle actions. Each
 * scene handler method receives its enclosing scene's wizard via the
 * `$this->wizard` property (typed `SceneWizard`).
 *
 * Mirrors `SceneWizard` (`aiogram/fsm/scene.py:442-655`).
 *
 * ## Lifecycle flow
 *
 *   enter() → setState(state) → onAction(Enter)
 *   leave() → [history.snapshot()] → onAction(Leave)
 *   exit()  → history.clear() → onAction(Exit) → manager.enter(null)
 *   back()  → leave(withHistory=false) → history.rollback() → manager.enter(prev)
 *   retake() → goto(sceneConfig.state)
 *   goto(target) → leave() → manager.enter(target)
 *
 * ## Data delegation
 *
 * `setData`, `getData`, `getValue`, `updateData`, and `clearData` all
 * delegate directly to the injected `FsmContext`.
 *
 * ## SceneConfig.actions shape
 *
 * ```
 * actions[SceneAction::Enter->value]['message'] = callable
 * ```
 *
 * `onAction()` resolves `actions[$action->value][$updateType]` and calls
 * the matching callable with `($this->scene, $this->event, ...$data, ...$kwargs)`.
 */
final class SceneWizard
{
  /**
   * The scene instance this wizard is bound to.
   *
   * Populated after construction by the framework (post-init injection).
   * `null` until the framework sets it; `onAction()` throws `SceneException`
   * if called while still `null`.
   */
  public ?Scene $scene = null;

  /**
   * @param SceneConfig $sceneConfig Immutable scene configuration record.
   * @param SceneManagerInterface $manager Manager that drives scene entry.
   * @param FsmContext $state FSM context for state + data operations.
   * @param string $updateType Telegram update-type key (e.g. `'message'`).
   * @param object $event The raw Telegram event object.
   * @param array<string, mixed> $data Dispatcher kwargs bag (mutable).
   */
  public function __construct(
    public readonly SceneConfig $sceneConfig,
    public readonly SceneManagerInterface $manager,
    public readonly FsmContext $state,
    public readonly string $updateType,
    public readonly object $event,
    /** @var array<string, mixed> */
    public array $data,
  ) {}

  // ------------------------------------------------------------------ //
  // Transition methods
  // ------------------------------------------------------------------ //

  /**
   * Enter the scene: set FSM state, optionally reset data/history, then
   * dispatch the `Enter` lifecycle action.
   *
   * Mirrors `SceneWizard.enter()` (`aiogram/fsm/scene.py:474-484`).
   *
   * @param mixed ...$kwargs Extra kwargs forwarded to the action handler.
   *                         Integer-keyed entries are silently dropped to
   *                         prevent PHP's "positional after named" unpack Error.
   */
  public function enter(mixed ...$kwargs): void
  {
    $named = self::namedOnly($kwargs);

    if ($this->sceneConfig->resetDataOnEnter === true) {
      $this->state->setData([]);
    }

    if ($this->sceneConfig->resetHistoryOnEnter === true) {
      $this->manager->history()->clear();
    }

    $this->state->setState($this->sceneConfig->state);
    $this->onAction(SceneAction::Enter, ...$named);
  }

  /**
   * Leave the scene: optionally snapshot history, then dispatch `Leave`.
   *
   * Mirrors `SceneWizard.leave()` (`aiogram/fsm/scene.py:486-490`).
   *
   * @param bool $withHistory When `true` (default), push the current scene
   *                          state onto the history stack before leaving.
   * @param mixed ...$kwargs Extra kwargs forwarded to the action handler.
   *                         Integer-keyed entries are silently dropped.
   */
  public function leave(bool $withHistory = true, mixed ...$kwargs): void
  {
    $named = self::namedOnly($kwargs);

    if ($withHistory) {
      $this->manager->history()->snapshot();
    }

    $this->onAction(SceneAction::Leave, ...$named);
  }

  /**
   * Exit the FSM entirely: clear history, dispatch `Exit`, then enter `null`.
   *
   * Mirrors `SceneWizard.exit()` (`aiogram/fsm/scene.py:492-497`).
   *
   * @param mixed ...$kwargs Extra kwargs forwarded to the action handler.
   *                         Integer-keyed entries are silently dropped.
   */
  public function exit(mixed ...$kwargs): void
  {
    $named = self::namedOnly($kwargs);
    $this->manager->history()->clear();
    $this->onAction(SceneAction::Exit, ...$named);
    $this->manager->enter(null, false, ...$named);
  }

  /**
   * Roll back to the previous scene: leave without snapshot, then
   * re-enter the scene from history.
   *
   * Mirrors `SceneWizard.back()` (`aiogram/fsm/scene.py:499-504`).
   *
   * @param mixed ...$kwargs Extra kwargs forwarded to the action handler.
   *                         Integer-keyed entries are silently dropped.
   */
  public function back(mixed ...$kwargs): void
  {
    $named = self::namedOnly($kwargs);
    $this->leave(false, ...$named);
    $newScene = $this->manager->history()->rollback();
    $this->manager->enter($newScene, false, ...$named);
  }

  /**
   * Re-enter the current scene (restart from the beginning).
   *
   * Requires that the scene has a configured state (i.e.
   * `$sceneConfig->state !== null`). A scene without a state cannot be
   * re-entered — calling `retake()` on it is a programming error and throws
   * `SceneException`.
   *
   * Mirrors `SceneWizard.retake()` (`aiogram/fsm/scene.py:506-508`).
   *
   * @param mixed ...$kwargs Extra kwargs forwarded to the action handler.
   *                         Integer-keyed entries are silently dropped.
   *
   * @throws SceneException When the scene has no configured state.
   */
  public function retake(mixed ...$kwargs): void
  {
    if ($this->sceneConfig->state === null) {
      throw new SceneException('Cannot retake() on a scene with no state.');
    }

    $this->goto($this->sceneConfig->state, ...$kwargs);
  }

  /**
   * Transition to `$scene`: leave (with history snapshot), then enter
   * the target scene.
   *
   * Mirrors `SceneWizard.goto()` (`aiogram/fsm/scene.py:510-513`).
   *
   * @param class-string<Scene>|State|string $scene Target scene.
   * @param mixed ...$kwargs Extra kwargs forwarded to the action handler.
   *                         Integer-keyed entries are silently dropped.
   */
  public function goto(State|string $scene, mixed ...$kwargs): void
  {
    $named = self::namedOnly($kwargs);
    $this->leave(true, ...$named);
    $this->manager->enter($scene, false, ...$named);
  }

  // ------------------------------------------------------------------ //
  // Data accessors — delegate to FsmContext
  // ------------------------------------------------------------------ //

  /**
   * Replace the FSM data payload entirely.
   *
   * Delegates to `FsmContext::setData`.
   *
   * @param array<string, mixed> $data
   */
  public function setData(array $data): void
  {
    $this->state->setData($data);
  }

  /**
   * Retrieve the current FSM data payload.
   *
   * Delegates to `FsmContext::getData`.
   *
   * @return array<string, mixed>
   */
  public function getData(): array
  {
    return $this->state->getData();
  }

  /**
   * Read a single value from the FSM data payload.
   *
   * Delegates to `FsmContext::getValue`.
   *
   * @param string $key Key within the data payload.
   * @param mixed $default Returned when `$key` is absent.
   *
   * @return mixed
   */
  public function getValue(string $key, mixed $default = null): mixed
  {
    return $this->state->getValue($key, $default);
  }

  /**
   * Merge data into the existing FSM data payload.
   *
   * Delegates to `FsmContext::updateData`.
   *
   * @param ?array<string, mixed> $data Optional explicit data to merge.
   * @param mixed ...$kwargs Additional named key/value pairs.
   *
   * @return array<string, mixed> The merged data map as persisted.
   */
  public function updateData(?array $data = null, mixed ...$kwargs): array
  {
    return $this->state->updateData($data, ...$kwargs);
  }

  /**
   * Clear the FSM data payload (set to empty array).
   *
   * Delegates to `FsmContext::setData([])`.
   */
  public function clearData(): void
  {
    $this->state->setData([]);
  }

  // ------------------------------------------------------------------ //
  // Internal
  // ------------------------------------------------------------------ //

  /**
   * Dispatch a lifecycle action to the registered handler for this
   * wizard's `$updateType`, if one exists.
   *
   * Mirrors `SceneWizard._on_action()` (`aiogram/fsm/scene.py:515-527`).
   *
   * Resolution:
   * 1. Look up `sceneConfig->actions[$action->value]` (the per-action map).
   * 2. Look up `[$this->updateType]` within that map to find the callable.
   * 3. Call it with `($this->scene, $this->event, ...$this->data, ...$kwargs)`.
   *
   * @param SceneAction $action The lifecycle action to dispatch.
   * @param mixed ...$kwargs Extra kwargs forwarded to the callable.
   *
   * @return bool `true` when a handler was found and called; `false` otherwise.
   *
   * @throws SceneException When `$this->scene` has not been set yet.
   */
  private function onAction(SceneAction $action, mixed ...$kwargs): bool
  {
    if ($this->scene === null) {
      throw new SceneException('SceneWizard::$scene must be set before dispatching actions.');
    }

    $actionMap = $this->sceneConfig->actions[$action->name] ?? null;

    if ($actionMap === null) {
      return false;
    }

    $handler = $actionMap[$this->updateType] ?? null;

    if ($handler === null) {
      return false;
    }

    $merged = self::namedOnly(array_merge($this->data, $kwargs));
    ($handler)($this->scene, $this->event, ...$merged);

    return true;
  }

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  /**
   * Strip integer-keyed entries from a kwarg bag before spread.
   *
   * PHP throws "Cannot use positional argument after named argument during
   * unpacking" if an array spread contains any integer-keyed element alongside
   * string-keyed (named) ones. User-supplied `...$kwargs` bags may contain
   * integer keys from generic data arrays. Filtering them here keeps all
   * public lifecycle surfaces safe regardless of caller origin.
   *
   * @param array<int|string, mixed> $kwargs
   *
   * @return array<string, mixed>
   */
  private static function namedOnly(array $kwargs): array
  {
    return array_filter($kwargs, 'is_string', ARRAY_FILTER_USE_KEY);
  }
}
