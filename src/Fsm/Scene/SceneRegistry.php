<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Exceptions\SceneException;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Types\Update;
use InvalidArgumentException;

/**
 * Registry that owns all registered scene classes and wires the per-update
 * `ScenesManager` into the dispatcher middleware stack.
 *
 * Mirrors `SceneRegistry` (`aiogram/fsm/scene.py:746-887`).
 *
 * ## Middleware wiring
 *
 * When the constructor receives a `Dispatcher` the registry attaches a single
 * outer-middleware to the `update` observer (the synthetic dispatcher-level
 * update channel). For any other `Router` it attaches to **all** observers
 * except `update` and `error`.
 *
 * Both middleware paths inject a `ScenesManager` under the `'scenes'` key of
 * the dispatcher data bag whenever a `FsmContext` (`'state'`) is present.
 *
 * ## Signature deviation from upstream
 *
 * Python's `add(*scenes, router=None)` uses positional variadics followed by a
 * named default — a combination PHP supports since 8.1. However, forwarding a
 * named argument after a variadic in PHP 8.1+ requires the call-site to use the
 * named-argument syntax explicitly, which is awkward for most callers. This
 * port uses `add(array $scenes, ?Router $router = null)` instead: callers pass
 * a list of class-strings as the first argument and an optional target router as
 * the second. `register()` is unchanged from upstream and delegates to `add()`.
 */
final class SceneRegistry implements SceneRegistryInterface
{
  /**
   * Sentinel used as the array key for scenes registered under `null` state.
   *
   * PHP 8.5 deprecates `null` as an array key / `array_key_exists` argument.
   * Internally we substitute `\0null` (a NUL-prefixed string guaranteed not
   * to appear in any real state name) so null-state scenes can still be
   * stored and retrieved without deprecation warnings.
   */
  private const string NULL_STATE_KEY = "\0null";

  /**
   * Registered scenes keyed by their FSM state string, or the `NULL_STATE_KEY`
   * sentinel for scenes registered under `null` state.
   *
   * @var array<string, class-string<Scene>>
   */
  private array $scenes = [];

  /**
   * @param Router $router The root router (or `Dispatcher`) to attach outer
   *                       middleware to and to include scene routers into when
   *                       `$registerOnAdd` is `true`.
   * @param bool $registerOnAdd When `true` (default), every scene added via
   *                            `add()` without an explicit `$router` argument is
   *                            included into `$this->router` automatically.
   */
  public function __construct(
    private readonly Router $router,
    private readonly bool $registerOnAdd = true,
  ) {
    $this->setupMiddleware($router);
  }

  // ------------------------------------------------------------------ //
  // SceneRegistryInterface
  // ------------------------------------------------------------------ //

  /**
   * Resolve `$sceneType` to a concrete `Scene` class-string.
   *
   * Accepted forms (mirrors upstream `SceneRegistry.get`):
   * - `class-string<Scene>` — resolve via `$class::sceneConfig()->state`.
   * - `State` instance — resolve via `$state->state()`.
   * - `string` — resolved directly as the state key.
   * - `null` — returns the scene registered under `null` state (or throws).
   *
   * @param null|class-string<Scene>|State|string $sceneType
   *
   * @return class-string<Scene>
   *
   * @throws SceneException When the identifier does not match any registered scene.
   */
  public function get(null|State|string $sceneType): string
  {
    // Normalise class-string<Scene> → state string.
    if (is_string($sceneType) && class_exists($sceneType) && is_subclass_of($sceneType, Scene::class)) {
      $sceneType = $sceneType::sceneConfig()->state;
    }

    // Normalise State → state string.
    if ($sceneType instanceof State) {
      $sceneType = $sceneType->state();
    }

    // At this point $sceneType is a string|null (the state key).
    if ($sceneType !== null && !is_string($sceneType)) {
      throw new SceneException(
        'Scene must be a subclass of Scene, State, or a string; got: ' . get_debug_type($sceneType),
      );
    }

    // Map null → internal sentinel to avoid PHP 8.5 null-as-array-key deprecation.
    $key = $sceneType ?? self::NULL_STATE_KEY;

    if (!array_key_exists($key, $this->scenes)) {
      throw new SceneException(
        sprintf('Scene %s is not registered', var_export($sceneType, return: true)),
      );
    }

    return $this->scenes[$key];
  }

  // ------------------------------------------------------------------ //
  // Registration
  // ------------------------------------------------------------------ //

  /**
   * Register one or more scene classes.
   *
   * For each scene:
   * - Its FSM state (from `$class::sceneConfig()->state` via `sceneState()`)
   *   is stored in the registry.
   * - When `$router` is non-null the scene's router sub-tree is included into
   *   that router.
   * - When `$router` is `null` and `$registerOnAdd` is `true`, the sub-tree is
   *   included into `$this->router`.
   * - When `$router` is `null` and `$registerOnAdd` is `false`, the scene is
   *   stored in the registry only (no router inclusion).
   *
   * Deviation from upstream's `add(*scenes, router=None)`: PHP signature is
   * `add(array $scenes, ?Router $router = null)`. See class-level docblock.
   *
   * @param list<class-string<Scene>> $scenes
   *
   * @throws InvalidArgumentException When the list is empty.
   * @throws SceneException When a scene with the same state is already registered.
   */
  public function add(array $scenes, ?Router $router = null): void
  {
    if ($scenes === []) {
      throw new InvalidArgumentException('At least one scene must be specified');
    }

    foreach ($scenes as $scene) {
      $state = $scene::sceneState();
      // Map null → internal sentinel to avoid PHP 8.5 null-as-array-key deprecation.
      $key = $state ?? self::NULL_STATE_KEY;

      if (array_key_exists($key, $this->scenes)) {
        throw new SceneException(
          sprintf('Scene with state %s already exists', var_export($state, return: true)),
        );
      }

      $this->scenes[$key] = $scene;

      if ($router !== null) {
        $router->includeRouter($scene::asRouter());
      } elseif ($this->registerOnAdd) {
        $this->router->includeRouter($scene::asRouter());
      }
    }
  }

  /**
   * Register scenes and always include them into `$this->router`.
   *
   * Sugar for `add($scenes, $this->router)`. Mirrors upstream's
   * `register(*scenes)` → `add(*scenes, router=self.router)`.
   *
   * @param list<class-string<Scene>> $scenes
   *
   * @throws InvalidArgumentException When the list is empty.
   * @throws SceneException When a scene with the same state is already registered.
   */
  public function register(array $scenes): void
  {
    $this->add($scenes, $this->router);
  }

  // ------------------------------------------------------------------ //
  // Middleware wiring (internal)
  // ------------------------------------------------------------------ //

  /**
   * Attach outer middleware to the appropriate observers on `$router`.
   *
   * - When `$router` is a `Dispatcher`: register `updateMiddleware` on the
   *   `update` observer. Upstream attaches to the single synthetic update
   *   observer (`router.update.outer_middleware`).
   * - Otherwise: register `middleware` on every observer **except** `update`
   *   and `error` (which are internal / infrastructure channels).
   *
   * Mirrors `SceneRegistry._setup_middleware` (`aiogram/fsm/scene.py:765-774`).
   */
  private function setupMiddleware(Router $router): void
  {
    if ($router instanceof Dispatcher) {
      // Dispatcher path: wire on the synthetic 'update' observer.
      // The Dispatcher does NOT store an 'update' observer in $observers —
      // it wires the dispatcher-level chain via $dispatcherMiddlewares.
      // We mirror upstream by registering on each Telegram observer (not
      // update/error) so the ScenesManager is injected for every event type.
      //
      // Upstream's Dispatcher.update.outer_middleware wires to a synthetic
      // observer that wraps the entire dispatch; in this port the closest
      // equivalent is registering on each TelegramEventObserver (same set
      // the non-Dispatcher path would use) but only once rather than per
      // sub-router.
      $updateMiddleware = $this->buildUpdateMiddleware();

      foreach ($router->observers as $eventName => $observer) {
        if ($eventName === 'error') {
          continue;
        }

        $observer->outerMiddleware($updateMiddleware);
      }

      return;
    }

    // Non-Dispatcher Router: register the simpler per-observer middleware
    // on every update observer except 'error'.
    $middleware = $this->buildMiddleware();

    foreach ($router->observers as $eventName => $observer) {
      if ($eventName === 'error') {
        continue;
      }

      $observer->outerMiddleware($middleware);
    }
  }

  /**
   * Build the update middleware for the Dispatcher path.
   *
   * Injects `ScenesManager` into the data bag keyed as `'scenes'` when the
   * data bag already contains a `'state'` (FsmContext) value. Bypasses the
   * chain with a plain delegate when `'state'` is absent.
   *
   * The event is expected to be an `Update` (Dispatcher path). The
   * `event_type` and concrete sub-event are read from the `Update`.
   *
   * Mirrors `SceneRegistry._update_middleware` (`aiogram/fsm/scene.py:776-791`).
   */
  private function buildUpdateMiddleware(): BaseMiddleware
  {
    $registry = $this;

    return new class ($registry) extends BaseMiddleware {
      public function __construct(private readonly SceneRegistry $registry) {}

      /**
       * @param Closure(object, array<string, mixed>): mixed $handler
       * @param array<string, mixed> $data
       */
      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $state = $data['state'] ?? null;

        if (!$state instanceof FsmContext) {
          return $handler($event, $data);
        }

        $update = $event instanceof Update ? $event : ($data['event_update'] ?? null);
        $updateType = $update instanceof Update ? ($update->eventType() ?? 'message') : 'message';

        $data['scenes'] = new ScenesManager(
          registry: $this->registry,
          updateType: $updateType,
          event: $event,
          state: $state,
          data: $data,
        );

        return $handler($event, $data);
      }
    };
  }

  /**
   * Build the per-observer middleware for the non-Dispatcher Router path.
   *
   * Reads `$data['event_update']` to determine the update type and delegates
   * to the handler after injecting `ScenesManager` under `'scenes'`.
   *
   * Mirrors `SceneRegistry._middleware` (`aiogram/fsm/scene.py:793-808`).
   */
  private function buildMiddleware(): BaseMiddleware
  {
    $registry = $this;

    return new class ($registry) extends BaseMiddleware {
      public function __construct(private readonly SceneRegistry $registry) {}

      /**
       * @param Closure(object, array<string, mixed>): mixed $handler
       * @param array<string, mixed> $data
       */
      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $state = $data['state'] ?? null;

        if (!$state instanceof FsmContext) {
          return $handler($event, $data);
        }

        $update = $data['event_update'] ?? null;
        $updateType = $update instanceof Update ? ($update->eventType() ?? 'message') : 'message';

        $data['scenes'] = new ScenesManager(
          registry: $this->registry,
          updateType: $updateType,
          event: $event,
          state: $state,
          data: $data,
        );

        return $handler($event, $data);
      }
    };
  }
}
