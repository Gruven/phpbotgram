<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

use Gruven\PhpBotGram\Fsm\Exception\SceneException;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\SceneWizard;
use Gruven\PhpBotGram\Fsm\State;

/**
 * Per-update scene transition front-end.
 *
 * `ScenesManager` is the entry point for wizard-style navigation. It resolves
 * the current active scene (by looking up the FSM state in the registry),
 * instantiates scene objects, and delegates lifecycle calls (`enter`, `exit`)
 * through `SceneWizard`.
 *
 * Mirrors `ScenesManager` (`aiogram/fsm/scene.py:659-743`).
 *
 * ## Responsibilities
 *
 * - `enter($scene)` — exits the currently active scene (if any) and enters the
 *   requested one.  When `$checkActive` is `false` the exit step is skipped
 *   (used by `SceneWizard` for chained transitions that have already handled
 *   leaving the old scene).
 * - `close()` — exits the currently active scene without transitioning to a
 *   new one.
 * - `history()` — accessor for the bound `HistoryManager` instance.
 *
 * ## Registry contract
 *
 * The injected `SceneRegistryInterface::get()` resolves a class-string, `State`
 * instance, or bare state string to a concrete `class-string<Scene>`.  It
 * throws `SceneException` when the identifier is unknown (including `null`).
 * `ScenesManager` relies on this exception to detect the "clear FSM state"
 * path: when `$scene` is `null` and `get()` throws, FSM state is cleared
 * instead of entering a new scene.
 */
final class ScenesManager implements SceneManagerInterface
{
  /**
   * History manager for this update session.
   */
  private readonly HistoryManager $historyManager;

  /**
   * @param SceneRegistryInterface $registry Scene-class resolver.
   * @param string $updateType Telegram update-type key (e.g. `'message'`).
   * @param object $event The raw Telegram event object.
   * @param FsmContext $state FSM context for state + data operations.
   * @param array<string, mixed> $data Dispatcher kwargs bag.
   */
  public function __construct(
    private readonly SceneRegistryInterface $registry,
    private readonly string $updateType,
    private readonly object $event,
    private readonly FsmContext $state,
    /** @var array<string, mixed> */
    private array $data,
  ) {
    $this->historyManager = new HistoryManager($this->state);
  }

  // ------------------------------------------------------------------ //
  // SceneHandlerWrapper accessors (internal to Fsm namespace)
  // ------------------------------------------------------------------ //

  /**
   * Return the update-type key this manager was created for.
   *
   * Used by `SceneHandlerWrapper` to populate the `SceneWizard`'s
   * `$updateType` field without requiring access to the private property.
   *
   * Mirrors the `update_type` field on `ScenesManager`
   * (`aiogram/fsm/scene.py:659`).
   */
  public function updateType(): string
  {
    return $this->updateType;
  }

  /**
   * Merge additional data into this manager's data bag.
   *
   * Used by `SceneHandlerWrapper` to inject the full dispatcher kwargs bag
   * so the scene wizard and lifecycle actions have access to `bot`,
   * `event_context`, etc.
   *
   * Mirrors `scenes.data = {**scenes.data, **kwargs}` in
   * `SceneHandlerWrapper.__call__` (`aiogram/fsm/scene.py:262`).
   *
   * @param array<string, mixed> $extra
   */
  public function mergeData(array $extra): void
  {
    $this->data = [...$this->data, ...$extra];
  }

  // ------------------------------------------------------------------ //
  // SceneManagerInterface
  // ------------------------------------------------------------------ //

  /**
   * Access the history manager for this scene session.
   *
   * Mirrors `ScenesManager.history` (implicit property, `aiogram/fsm/scene.py:665`).
   */
  public function history(): HistoryManagerInterface
  {
    return $this->historyManager;
  }

  /**
   * Transition to the given scene (or clear FSM state when `null`).
   *
   * When `$checkActive` is `true` (the default) and a scene is currently
   * active, its `SceneWizard::exit()` is invoked before entering the new scene.
   *
   * Passing `null` for `$scene` combined with an unknown registry entry causes
   * the FSM state to be cleared via `FsmContext::setState(null)`.  Any extra
   * `$kwargs` are merged into the data bag and forwarded to lifecycle methods.
   *
   * Mirrors `ScenesManager.enter` (`aiogram/fsm/scene.py:687-706`).
   *
   * @param null|class-string<Scene>|State|string $scene Target scene.
   * @param bool $checkActive Skip the "exit active scene" guard when `false`.
   * @param mixed ...$kwargs Additional context merged into the data bag.
   */
  public function enter(null|State|string $scene, bool $checkActive = true, mixed ...$kwargs): void
  {
    if ($kwargs !== []) {
      /** @var array<string, mixed> $kwargs */
      $this->data = array_merge($this->data, $kwargs);
    }

    if ($checkActive) {
      $active = $this->getActiveScene();

      if ($active !== null) {
        $active->wizard->exit(...$kwargs);
      }
    }

    try {
      $sceneInstance = $this->getScene($scene);
    } catch (SceneException $e) {
      if ($scene !== null) {
        throw $e;
      }

      $this->state->setState(null);

      return;
    }

    $sceneInstance->wizard->enter(...$kwargs);
  }

  /**
   * Exit the currently active scene without entering a new one.
   *
   * When no scene is currently active this method is a no-op (matches upstream
   * Python behaviour where `close()` silently returns when `_get_active_scene`
   * yields `None`).
   *
   * Mirrors `ScenesManager.close` (`aiogram/fsm/scene.py:708-712`).
   *
   * @param mixed ...$kwargs Additional context forwarded to the exit handler.
   */
  public function close(mixed ...$kwargs): void
  {
    $scene = $this->getActiveScene();

    if ($scene !== null) {
      $scene->wizard->exit(...$kwargs);
    }
  }

  // ------------------------------------------------------------------ //
  // Private helpers
  // ------------------------------------------------------------------ //

  /**
   * Instantiate the scene identified by `$sceneType`.
   *
   * Resolves `$sceneType` through the registry and constructs a `SceneWizard`
   * + `Scene` pair with a circular back-reference.
   *
   * Mirrors `ScenesManager._get_scene` (`aiogram/fsm/scene.py:669-679`).
   *
   * @param null|class-string<Scene>|State|string $sceneType
   *
   * @throws SceneException When `$sceneType` cannot be resolved.
   */
  private function getScene(null|State|string $sceneType): Scene
  {
    /** @var class-string<Scene> $class */
    $class = $this->registry->get($sceneType);

    $sceneConfig = $class::sceneConfig();

    $wizard = new SceneWizard(
      sceneConfig: $sceneConfig,
      manager: $this,
      state: $this->state,
      updateType: $this->updateType,
      event: $this->event,
      data: $this->data,
    );

    /** @var Scene $sceneInstance */
    $sceneInstance = new $class($wizard);
    $wizard->scene = $sceneInstance;

    return $sceneInstance;
  }

  /**
   * Return the currently active `Scene` instance, or `null` if none.
   *
   * Reads the current FSM state and attempts to resolve it via the registry.
   * When the state is `null` or the registry does not know it, `null` is
   * returned rather than propagating a `SceneException`.
   *
   * Mirrors `ScenesManager._get_active_scene` (`aiogram/fsm/scene.py:681-685`).
   */
  private function getActiveScene(): ?Scene
  {
    $state = $this->state->getState();

    try {
      return $this->getScene($state);
    } catch (SceneException) {
      return null;
    }
  }
}
