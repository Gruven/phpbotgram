<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\Exception\SceneException;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\SceneAction;
use Gruven\PhpBotGram\Fsm\SceneWizard;

/**
 * Wraps a scene instance-method handler into a callable suitable for
 * registration with a `TelegramEventObserver`.
 *
 * Mirrors `SceneHandlerWrapper` (`aiogram/fsm/scene.py:235-294`).
 *
 * Responsibilities:
 * 1. Extract `state` (`FsmContext`) and `scenes` (`ScenesManager`) from the
 *    dispatcher kwargs bag; throw `SceneException` if either is missing.
 * 2. Instantiate the scene class with a fresh `SceneWizard` bound to the
 *    current event context.
 * 3. Call the wrapped handler method on the scene instance.
 * 4. If an `After` action was specified, execute it via the wizard.
 *
 * The `event_update` field is not required in the PHP port — the update type
 * is injected directly into `ScenesManager` by `SceneRegistry` middleware
 * before `SceneHandlerWrapper::__invoke` is called.  When `event_update` is
 * absent we default to `'message'` for robustness.
 */
final class SceneHandlerWrapper
{
  /**
   * @param class-string<Scene> $sceneClass The scene class to instantiate.
   * @param callable $handler The unbound handler method (receives `$scene` as
   *                          the first argument).
   * @param ?After $after Optional post-handler action.
   */
  public function __construct(
    /** @var class-string<Scene> */
    private readonly string $sceneClass,
    /** @var callable */
    private readonly mixed $handler,
    private readonly ?After $after = null,
  ) {}

  /**
   * Invoke the wrapped handler.
   *
   * Mirrors `SceneHandlerWrapper.__call__` (`aiogram/fsm/scene.py:246-284`).
   *
   * @throws SceneException When `'state'` or `'scenes'` are absent from kwargs.
   */
  public function __invoke(object $event, mixed ...$kwargs): mixed
  {
    // Normalise to string-keyed array.  `mixed ...$kwargs` admits integer keys
    // from positional spreads at call sites; those are not valid dispatcher
    // kwargs and must be discarded before passing to ScenesManager / SceneWizard
    // which require `array<string, mixed>`.
    $stringKwargs = [];

    foreach ($kwargs as $k => $v) {
      if (is_string($k)) {
        $stringKwargs[$k] = $v;
      }
    }

    $state = $stringKwargs['state'] ?? null;

    if (!$state instanceof FsmContext) {
      throw new SceneException(
        "Scene context key 'state' is not available. "
        . 'Ensure FSM is enabled and pipeline is intact.',
      );
    }

    $scenes = $stringKwargs['scenes'] ?? null;

    if (!$scenes instanceof ScenesManager) {
      throw new SceneException(
        "Scene context key 'scenes' is not available. "
        . 'Ensure FSM is enabled and pipeline is intact.',
      );
    }

    // Merge the incoming kwargs into the ScenesManager's data bag so the
    // scene has the full dispatcher context, mirroring:
    //   scenes.data = {**scenes.data, **kwargs}
    $scenes->mergeData($stringKwargs);

    // Determine the update type from the ScenesManager.
    $updateType = $scenes->updateType();

    // Build the SceneWizard for this dispatch (mirrors upstream lines 263-272).
    $sceneClass = $this->sceneClass;
    $sceneConfig = $sceneClass::sceneConfig();

    $wizard = new SceneWizard(
      sceneConfig: $sceneConfig,
      manager: $scenes,
      state: $state,
      updateType: $updateType,
      event: $event,
      data: $stringKwargs,
    );

    $sceneInstance = new $sceneClass($wizard);
    $wizard->scene = $sceneInstance;

    // Call the handler with the scene instance as the implicit $this.
    // Mirrors `await self.handler.call(scene, event, **kwargs)`.
    $result = ($this->handler)($sceneInstance, $event, ...$stringKwargs);

    // Execute the after-action if specified (mirrors lines 276-283).
    if ($this->after !== null) {
      $this->executeAfter($wizard, $this->after);
    }

    return $result;
  }

  /**
   * Execute an `After` action via the scene wizard.
   *
   * Mirrors `ActionContainer.execute` (`aiogram/fsm/scene.py:187-195`).
   */
  private function executeAfter(SceneWizard $wizard, After $after): void
  {
    match ($after->action) {
      SceneAction::Enter => $wizard->goto($after->scene ?? ''),
      SceneAction::Leave => $wizard->leave(),
      SceneAction::Exit  => $wizard->exit(),
      SceneAction::Back  => $wizard->back(),
    };
  }
}
