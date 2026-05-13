<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Exceptions\SceneException;
use Gruven\PhpBotGram\Filters\StateFilter;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnAttribute;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use Gruven\PhpBotGram\Fsm\Scene\HandlerContainer;
use Gruven\PhpBotGram\Fsm\Scene\SceneConfig;
use Gruven\PhpBotGram\Fsm\Scene\SceneHandlerWrapper;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Abstract base for all scene classes in the Scene subsystem.
 *
 * A scene encapsulates a logical conversation step — the user is "in" a
 * scene while the FSM state matches the scene's declared state.  Subclasses
 * declare their FSM state via the `#[SceneState]` class attribute and attach
 * handlers via the `#[On*]` method attributes (`OnMessage`, `OnCallbackQuery`,
 * etc. in `Scene/Attribute/`).
 *
 * Mirrors `Scene` (`aiogram/fsm/scene.py:297-441`).
 *
 * ## Usage
 *
 *   #[SceneState('greeting')]
 *   final class GreetingScene extends Scene
 *   {
 *       #[OnMessage]
 *       public function onMessage(object $message): void
 *       {
 *           // ...
 *       }
 *
 *       #[OnMessage(action: SceneAction::Enter)]
 *       public function onEnter(object $message): void
 *       {
 *           // ...
 *       }
 *   }
 *
 * ## Lifecycle stubs
 *
 * - `enter()` — invoked by the framework when the scene is entered.
 * - `leave()` — invoked when the scene is left via `SceneWizard::leave()`.
 * - `exit()`  — invoked when FSM is cleared via `SceneWizard::exit()`.
 * - `back()`  — invoked when rolling back via `SceneWizard::back()`.
 * - `retake()` — invoked when the scene is re-entered (e.g. loop iteration).
 *
 * Default implementations return `null`. Subclasses override as needed.
 * Returning `null` from a lifecycle hook is safe — the framework checks the
 * return type dynamically.
 *
 * ## SceneWizard
 *
 * The `$wizard` property is typed as `SceneWizard` (Task 5.9). Subclasses
 * receive a fully initialised wizard instance from the framework.
 */
abstract class Scene
{
  /**
   * The wizard that manages scene transitions for this instance.
   */
  public SceneWizard $wizard;

  /**
   * Per-class cache of reflection-built `SceneConfig` instances.
   *
   * Keyed by `static::class` so each subclass gets its own entry.
   * Populated lazily by `buildSceneConfig()` on first access.
   *
   * @var array<class-string<Scene>, SceneConfig>
   */
  private static array $cachedSceneConfig = [];

  /**
   * Construct a scene instance.
   *
   * @param SceneWizard $wizard Scene wizard that drives transitions.
   */
  public function __construct(SceneWizard $wizard)
  {
    $this->wizard = $wizard;
    $this->wizard->scene = $this;
  }

  // ------------------------------------------------------------------ //
  // Lifecycle stubs — subclasses override as needed
  // ------------------------------------------------------------------ //

  /**
   * Invoked by the framework when this scene is entered.
   *
   * Mirrors `Scene.enter()` (`aiogram/fsm/scene.py:400-404`).
   * Default: returns `null`. Subclasses may return `mixed`.
   */
  public function enter(mixed ...$kwargs): mixed
  {
    return null;
  }

  /**
   * Invoked when the scene is left via `SceneWizard::leave()`.
   *
   * Mirrors `Scene.leave()` (`aiogram/fsm/scene.py:406-408`).
   * Default: returns `null`.
   */
  public function leave(mixed ...$kwargs): mixed
  {
    return null;
  }

  /**
   * Invoked when FSM is cleared via `SceneWizard::exit()`.
   *
   * Mirrors `Scene.exit()` (`aiogram/fsm/scene.py:410-412`).
   * Default: returns `null`.
   */
  public function exit(mixed ...$kwargs): mixed
  {
    return null;
  }

  /**
   * Invoked when rolling back via `SceneWizard::back()`.
   *
   * Mirrors `Scene.back()` (`aiogram/fsm/scene.py:414-416`).
   * Default: returns `null`.
   */
  public function back(mixed ...$kwargs): mixed
  {
    return null;
  }

  /**
   * Invoked when the scene is re-entered (e.g., after a looping step).
   *
   * No direct upstream equivalent — added for the PHP port's "retake"
   * concept used in wizard-style multi-step scenes.
   * Default: returns `null`.
   */
  public function retake(mixed ...$kwargs): mixed
  {
    return null;
  }

  // ------------------------------------------------------------------ //
  // Router / handler building — Task 5.12
  // ------------------------------------------------------------------ //

  /**
   * Return a `Router` sub-tree that registers this scene's handlers.
   *
   * Mirrors `Scene.as_router()` (`aiogram/fsm/scene.py:407-421`).
   *
   * The router name defaults to:
   *   "Scene '<FQCN>' for state '<state>'"
   *
   * @param ?string $name Optional router name override.
   */
  public static function asRouter(?string $name = null): Router
  {
    $config = static::sceneConfig();

    if ($name === null) {
      $state = $config->state !== null ? "'{$config->state}'" : 'null';
      $name = "Scene '" . static::class . "' for state {$state}";
    }

    $router = new Router($name);
    static::addToRouter($router);

    return $router;
  }

  /**
   * Wire this scene's handlers into an existing router.
   *
   * For each `HandlerContainer` in `sceneConfig()->handlers`:
   * - Create a `SceneHandlerWrapper` around the handler.
   * - Register it with the matching observer (keyed by `$handler->name`,
   *   which is the Telegram event-type string such as `'message'`).
   * - Track which observer names were used.
   *
   * After all handlers are registered, add a `StateFilter` as a global
   * observer filter on every used observer (except `callback_query` when
   * `callbackQueryWithoutState` is `true`).
   *
   * Mirrors `Scene.add_to_router()` (`aiogram/fsm/scene.py:379-405`).
   */
  public static function addToRouter(Router $router): void
  {
    $config = static::sceneConfig();

    /** @var array<string, bool> $usedObservers */
    $usedObservers = [];

    foreach ($config->handlers as $handler) {
      $observerName = $handler->name;

      if (!isset($router->observers[$observerName])) {
        continue;
      }

      $observer = $router->observers[$observerName];

      // Build the wrapper for this handler entry.
      $wrapper = new SceneHandlerWrapper(
        sceneClass: static::class,
        handler: $handler->handler,
        after: $handler->after,
      );

      // TelegramEventObserver::register() requires a Closure.
      $wrapperClosure = Closure::fromCallable($wrapper);

      // Convert per-handler Filter instances to Closures.
      $filterClosures = [];

      foreach ($handler->filters as $filter) {
        $filterClosures[] = Closure::fromCallable($filter);
      }

      $observer->register($wrapperClosure, $filterClosures);

      $usedObservers[$observerName] = true;
    }

    // Register a StateFilter as a global filter on each used observer.
    // Mirrors `router.observers[name].filter(StateFilter(scene_config.state))`.
    $stateFilter = new StateFilter($config->state);
    $stateFilterClosure = Closure::fromCallable($stateFilter);

    foreach (array_keys($usedObservers) as $observerName) {
      // Skip callback_query when callbackQueryWithoutState is true.
      if ($config->callbackQueryWithoutState === true && $observerName === 'callback_query') {
        continue;
      }

      if (!isset($router->observers[$observerName])) {
        continue;
      }

      $router->observers[$observerName]->filter($stateFilterClosure);
    }
  }

  /**
   * Return a callable that enters this scene when invoked.
   *
   * The returned closure is suitable for registering as a handler on any
   * observer.  When invoked it calls `$scenes->enter(static::class, $checkActive)`
   * where `$scenes` is the `ScenesManager` injected by `SceneRegistry` middleware.
   *
   * Mirrors `Scene.as_handler()` (`aiogram/fsm/scene.py:423-438`).
   *
   * ## `$checkActive` — top-level parameter, not a kwarg
   *
   * `$checkActive` is declared as an explicit typed parameter rather than
   * flowing through `...$handlerKwargs`.  This avoids a PHP duplicate-named-arg
   * error that would crash production when a middleware-injected kwarg named
   * `checkActive` collides with the same-named key being forwarded positionally
   * to `ScenesManager::enter`.
   *
   *   // Correct — uses the dedicated parameter:
   *   MyScene::asHandler(checkActive: false)
   *
   * ## `checkActive` in the merged kwargs bag — silently dropped
   *
   * Any kwarg literally named `checkActive` that arrives via `$handlerKwargs`
   * or the middleware bag is removed from the merged array before the `enter()`
   * call.  This prevents the duplicate-named-arg crash for callers that
   * inadvertently include a `checkActive` key in their own kwargs payload.
   * The safety property from Cycle 1 is preserved: any *other* duplicate key
   * collision still produces a PHP Error at the `enter()` call site.
   *
   * @param bool $checkActive When `true` (default) the currently active scene
   *                          is exited before entering this one.  Pass `false`
   *                          to skip that step (e.g. for chained transitions).
   * @param mixed ...$handlerKwargs Extra kwargs merged into the `enter()` call.
   *
   * @return Closure(object, mixed...): void
   */
  public static function asHandler(bool $checkActive = true, mixed ...$handlerKwargs): Closure
  {
    $sceneClass = static::class;

    return static function (object $event, mixed ...$middlewareKwargs) use ($sceneClass, $checkActive, $handlerKwargs): void {
      $scenes = $middlewareKwargs['scenes'] ?? null;

      if (!$scenes instanceof ScenesManager) {
        throw new SceneException(
          "Scene context key 'scenes' is not available. "
          . 'Ensure FSM is enabled and pipeline is intact.',
        );
      }

      // Merge handler_kwargs into middleware_kwargs (middleware_kwargs wins
      // on key collision — mirrors Python's `{**handler_kwargs, **middleware_kwargs}`).
      // Retain only string-keyed entries so they flow into the variadic
      // `...$kwargs` parameter of `enter()`. Integer keys are stripped to
      // prevent positional conflicts.
      //
      // Defensive: strip reserved kwarg names that are already bound as
      // positional parameters in `ScenesManager::enter($scene, $checkActive, ...)`.
      // Keeping them in the bag produces PHP duplicate-named-arg Errors:
      //   - `scene` collides with the first positional arg to enter().
      //   - `checkActive` collides with the second positional arg to enter().
      /** @var array<string, mixed> $mergedKwargs */
      $mergedKwargs = [];

      foreach ([...$handlerKwargs, ...$middlewareKwargs] as $k => $v) {
        if (is_string($k)) {
          $mergedKwargs[$k] = $v;
        }
      }

      unset($mergedKwargs['scene'], $mergedKwargs['checkActive']);

      $scenes->enter($sceneClass, $checkActive, ...$mergedKwargs);
    };
  }

  // ------------------------------------------------------------------ //
  // SceneConfig accessor — reflection-based, cached per class
  // ------------------------------------------------------------------ //

  /**
   * Return the `SceneConfig` for this scene class.
   *
   * On first call the config is built via reflection (reading `#[SceneState]`
   * from the class and `#[On*]` attributes from public methods) and cached in
   * `self::$cachedSceneConfig[static::class]`. Subsequent calls return the
   * cached instance without re-reflecting.
   *
   * Subclasses that override this method explicitly (e.g. test fixtures that
   * hand-build a `SceneConfig`) bypass the reflection path entirely — the
   * override is called instead.
   *
   * Mirrors the `__scene_config__` class attribute populated in
   * `Scene.__init_subclass__` (`aiogram/fsm/scene.py:316-325`).
   */
  public static function sceneConfig(): SceneConfig
  {
    $class = static::class;

    return self::$cachedSceneConfig[$class] ??= self::buildSceneConfig();
  }

  // ------------------------------------------------------------------ //
  // Reflection helpers
  // ------------------------------------------------------------------ //

  /**
   * Return the FSM state string declared by the `#[SceneState]` attribute on
   * this class (or a subclass), or derive a default from the class short name.
   *
   * Resolution order:
   * 1. The `#[SceneState('explicit_state')]` attribute value (non-null).
   * 2. If the attribute is present but `$state` is `null`, fall back to the
   *    lowercase short class name (mirrors upstream's default where the state
   *    name equals the class name in snake_case).
   * 3. If the attribute is absent entirely, also fall back to the lowercase
   *    short class name.
   *
   * Mirrors the state-name resolution in `Scene.__init_subclass__`
   * (`aiogram/fsm/scene.py:318-322`).
   *
   * @return null|string The resolved FSM state string, or `null` if the
   *                     class has no short name (anonymous classes).
   */
  public static function sceneState(): ?string
  {
    $ref = new ReflectionClass(static::class);

    $attrs = $ref->getAttributes(SceneState::class);

    if ($attrs !== []) {
      /** @var SceneState $inst */
      $inst = $attrs[0]->newInstance();

      if ($inst->state !== null) {
        return $inst->state;
      }
    }

    // Default: lowercase short class name.
    $shortName = $ref->getShortName();

    return $shortName !== '' ? strtolower($shortName) : null;
  }

  // ------------------------------------------------------------------ //
  // Private — reflection-based SceneConfig builder
  // ------------------------------------------------------------------ //

  /**
   * Build a `SceneConfig` for this class by reflecting on `#[SceneState]`
   * (class) and `#[On*]` (method) attributes.
   *
   * Algorithm:
   * 1. Read `#[SceneState]` from the class → `$stateName`.
   * 2. For each public method, read all `#[On*]` attributes:
   *    - Attribute with `$action === null` → `HandlerContainer` entry in
   *      `$handlers` (ordinary event handler).
   *    - Attribute with `$action !== null` → entry in `$actions` keyed by
   *      `$action->name` (lifecycle action handler).
   * 3. Construct and return the `SceneConfig`.
   *
   * Mirrors `Scene.__init_subclass__` (`aiogram/fsm/scene.py:321-377`).
   */
  private static function buildSceneConfig(): SceneConfig
  {
    $class = static::class;
    $reflection = new ReflectionClass($class);

    // --- 1. Resolve state name ------------------------------------------
    $stateName = static::sceneState();

    // --- 2. Walk public methods for #[On*] attributes -------------------
    /** @var list<HandlerContainer> $handlers */
    $handlers = [];

    /** @var array<string, array<string, callable>> $actions */
    $actions = [];

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
      // Skip abstract, static, and constructor-related methods.
      if ($method->isAbstract() || $method->isStatic() || $method->isConstructor()) {
        continue;
      }

      $onAttributes = $method->getAttributes(OnAttribute::class, ReflectionAttribute::IS_INSTANCEOF);

      foreach ($onAttributes as $attrRef) {
        /** @var OnAttribute $attr */
        $attr = $attrRef->newInstance();

        // Build the unbound callable: a closure that, when invoked with
        // ($scene, $event, ...$kwargs), calls $method on $scene.
        $methodName = $method->getName();
        $handler = static function (Scene $scene, object $event, mixed ...$kwargs) use ($methodName): mixed {
          return $scene->{$methodName}($event, ...$kwargs);
        };

        if ($attr->action === null) {
          // Ordinary event handler → HandlerContainer.
          $handlers[] = new HandlerContainer(
            name: $attr->event,
            handler: $handler,
            filters: $attr->filters,
            after: $attr->after,
          );
        } else {
          // Lifecycle action handler → $actions[$actionName][$eventName].
          $actionName = $attr->action->name;
          $eventName = $attr->event;

          if (!isset($actions[$actionName])) {
            $actions[$actionName] = [];
          }

          $actions[$actionName][$eventName] = $handler;
        }
      }
    }

    return new SceneConfig(
      state: $stateName,
      handlers: $handlers,
      actions: $actions,
    );
  }
}
