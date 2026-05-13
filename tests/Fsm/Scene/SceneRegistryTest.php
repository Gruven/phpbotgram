<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Scene;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Fsm\Exception\SceneException;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\SceneConfig;
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistry;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Upstream `tests/test_fsm/test_scene.py::TestSceneRegistry` cases
 * deliberately not ported:
 *
 * - `TestSceneRegistry::test_include_*` dispatcher integration cases — require
 *   a running `Dispatcher` with async `feed_update`; covered structurally by
 *   `testDispatcherPathRegistersMiddlewareOnAllObserversExceptError` in this file.
 * - `TestSceneRegistry::test_set_*` state persistence cases — dispatcher
 *   integration: require full async FSM state machine loop.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class SceneRegistryTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  private function makeRouter(?string $name = null): Router
  {
    return new Router($name ?? 'test');
  }

  private function makeDispatcher(): Dispatcher
  {
    return new Dispatcher('test_dp');
  }

  private function makeContext(): FsmContext
  {
    return new FsmContext(
      new MemoryStorage(),
      new StorageKey(botId: 1, chatId: 1, userId: 1),
    );
  }

  /**
   * Return the list of outer-middleware counts keyed by observer name.
   *
   * @return array<string, int>
   */
  private function outerMiddlewareCounts(Router $router): array
  {
    $result = [];

    foreach ($router->observers as $name => $observer) {
      $count = count($observer->outerMiddleware);

      if ($count > 0) {
        $result[$name] = $count;
      }
    }

    return $result;
  }

  /**
   * Invoke the registered outer-middleware on the first non-error observer
   * of the given router, injecting the supplied data bag.
   *
   * Returns the data bag that reached the terminal handler so tests can
   * assert injected keys.
   *
   * @param array<string, mixed> $data
   *
   * @return array<string, mixed>
   */
  private function invokeFirstMiddleware(Router $router, array $data): array
  {
    foreach ($router->observers as $name => $observer) {
      if ($name === 'error') {
        continue;
      }

      if (count($observer->outerMiddleware) === 0) {
        continue;
      }

      $captured = [];

      $terminal = static function (object $event, array $d) use (&$captured): null {
        $captured = $d;

        return null;
      };

      $wrapped = $observer->outerMiddleware->wrap(Closure::fromCallable($terminal));
      $wrapped(new stdClass(), $data);

      return $captured;
    }

    return $data;
  }

  // ------------------------------------------------------------------ //
  // Constructor: middleware wiring — Dispatcher path
  // ------------------------------------------------------------------ //

  /**
   * When the constructor receives a `Dispatcher`, outer middleware is
   * registered on **all** observers except `error`.
   *
   * The Dispatcher path mirrors upstream's
   * `router.update.outer_middleware(self._update_middleware)` — in this port
   * that is represented by registering on all non-error TelegramEventObservers.
   */
  public function testDispatcherPathRegistersMiddlewareOnAllObserversExceptError(): void
  {
    $dp = $this->makeDispatcher();
    new SceneRegistry($dp);

    $counts = $this->outerMiddlewareCounts($dp);

    // Every observer except 'error' should have exactly 1 middleware.
    foreach ($dp->observers as $name => $observer) {
      if ($name === 'error') {
        self::assertSame(
          0,
          count($observer->outerMiddleware),
          "Observer 'error' must have no outer middleware",
        );
      } else {
        self::assertSame(
          1,
          count($observer->outerMiddleware),
          "Observer '{$name}' must have exactly 1 outer middleware (Dispatcher path)",
        );
      }
    }
  }

  // ------------------------------------------------------------------ //
  // Constructor: middleware wiring — Router path
  // ------------------------------------------------------------------ //

  /**
   * When the constructor receives a non-Dispatcher `Router`, outer middleware
   * is registered on every observer **except** `error`.
   */
  public function testRouterPathRegistersMiddlewareOnAllObserversExceptError(): void
  {
    $router = $this->makeRouter();
    new SceneRegistry($router);

    foreach ($router->observers as $name => $observer) {
      if ($name === 'error') {
        self::assertSame(
          0,
          count($observer->outerMiddleware),
          "Observer 'error' must have no outer middleware (Router path)",
        );
      } else {
        self::assertSame(
          1,
          count($observer->outerMiddleware),
          "Observer '{$name}' must have exactly 1 outer middleware (Router path)",
        );
      }
    }
  }

  // ------------------------------------------------------------------ //
  // add() — argument validation
  // ------------------------------------------------------------------ //

  /**
   * `add([])` throws `InvalidArgumentException`.
   */
  public function testAddEmptyListThrowsInvalidArgumentException(): void
  {
    $registry = new SceneRegistry($this->makeRouter());

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('At least one scene must be specified');

    $registry->add([]);
  }

  // ------------------------------------------------------------------ //
  // add() — state registration
  // ------------------------------------------------------------------ //

  /**
   * `add([RegistryScene1::class])` stores the scene by its state string.
   * `get(state)` returns the class.
   */
  public function testAddRegistersSceneByStateString(): void
  {
    $registry = new SceneRegistry($this->makeRouter(), registerOnAdd: false);
    $registry->add([RegistryScene1::class]);

    $resolved = $registry->get(RegistryScene1::STATE);
    self::assertSame(RegistryScene1::class, $resolved);
  }

  /**
   * Adding two scenes registers both independently.
   */
  public function testAddRegistersMultipleScenes(): void
  {
    $registry = new SceneRegistry($this->makeRouter(), registerOnAdd: false);
    $registry->add([RegistryScene1::class, RegistryScene2::class]);

    self::assertSame(RegistryScene1::class, $registry->get(RegistryScene1::STATE));
    self::assertSame(RegistryScene2::class, $registry->get(RegistryScene2::STATE));
  }

  /**
   * Adding the same state a second time throws `SceneException`.
   */
  public function testAddDuplicateStateThrowsSceneException(): void
  {
    $registry = new SceneRegistry($this->makeRouter(), registerOnAdd: false);
    $registry->add([RegistryScene1::class]);

    $this->expectException(SceneException::class);

    // Add again — same class, same state.
    $registry->add([RegistryScene1::class]);
  }

  // ------------------------------------------------------------------ //
  // add() — router inclusion (registerOnAdd=true)
  // ------------------------------------------------------------------ //

  /**
   * When `$registerOnAdd=true`, `add()` includes the scene's router into
   * `$this->router` (the parent passed to the constructor).
   */
  public function testAddWithRegisterOnAddIncludesSceneRouter(): void
  {
    $parent = $this->makeRouter('parent');
    $registry = new SceneRegistry($parent, registerOnAdd: true);

    $registry->add([RegistryScene1::class]);

    // The scene's router should now appear as a sub-router of the parent.
    self::assertCount(1, $parent->subRouters);
    self::assertSame(RegistryScene1::STATE, $parent->subRouters[0]->name);
  }

  /**
   * When `$registerOnAdd=false` and no explicit `$router` is given, `add()`
   * does NOT include the scene's router.
   */
  public function testAddWithRegisterOnAddFalseDoesNotIncludeRouter(): void
  {
    $parent = $this->makeRouter('parent');
    $registry = new SceneRegistry($parent, registerOnAdd: false);

    $registry->add([RegistryScene1::class]);

    self::assertCount(0, $parent->subRouters);
  }

  /**
   * `add([..], $explicitRouter)` includes the scene's router into the
   * explicit router, not the parent.
   */
  public function testAddWithExplicitRouterIncludesIntoExplicitRouter(): void
  {
    $parent = $this->makeRouter('parent');
    $explicit = $this->makeRouter('explicit');
    $registry = new SceneRegistry($parent, registerOnAdd: false);

    $registry->add([RegistryScene1::class], $explicit);

    self::assertCount(0, $parent->subRouters);
    self::assertCount(1, $explicit->subRouters);
  }

  // ------------------------------------------------------------------ //
  // register()
  // ------------------------------------------------------------------ //

  /**
   * `register()` is sugar for `add($scenes, $this->router)` — scene is
   * included into the constructor's router.
   */
  public function testRegisterIncludesIntoConstructorRouter(): void
  {
    $parent = $this->makeRouter('parent');
    // Use registerOnAdd=false so we can verify register() takes the $this->router path.
    $registry = new SceneRegistry($parent, registerOnAdd: false);

    $registry->register([RegistryScene1::class]);

    self::assertCount(1, $parent->subRouters);
  }

  // ------------------------------------------------------------------ //
  // get() — resolution paths
  // ------------------------------------------------------------------ //

  /**
   * `get(string)` resolves directly by the state key.
   */
  public function testGetByStateString(): void
  {
    $registry = new SceneRegistry($this->makeRouter(), registerOnAdd: false);
    $registry->add([RegistryScene1::class]);

    self::assertSame(RegistryScene1::class, $registry->get(RegistryScene1::STATE));
  }

  /**
   * `get(State)` resolves via `$state->state()`.
   */
  public function testGetByStateObject(): void
  {
    $registry = new SceneRegistry($this->makeRouter(), registerOnAdd: false);
    $registry->add([RegistryScene1::class]);

    $state = new State(RegistryScene1::STATE);
    // Force qualification off (standalone State without group).
    // State::state() for '@:registry_scene_1' would not match, so use groupName.
    $state2 = new State(state: RegistryScene1::STATE, groupName: '');

    // Build a State that yields exactly RegistryScene1::STATE from state().
    // Since we declared an explicit string, we use a minimal State wrapper.
    $stateObj = $this->makeStateWithValue(RegistryScene1::STATE);

    self::assertSame(RegistryScene1::class, $registry->get($stateObj));
  }

  /**
   * `get(class-string<Scene>)` resolves via `$class::sceneConfig()->state`.
   */
  public function testGetBySceneClassString(): void
  {
    $registry = new SceneRegistry($this->makeRouter(), registerOnAdd: false);
    $registry->add([RegistryScene1::class]);

    self::assertSame(RegistryScene1::class, $registry->get(RegistryScene1::class));
  }

  /**
   * `get(null)` returns the scene registered under null state.
   */
  public function testGetNullStateReturnsNullKeyScene(): void
  {
    $registry = new SceneRegistry($this->makeRouter(), registerOnAdd: false);
    $registry->add([RegistryNullStateScene::class]);

    self::assertSame(RegistryNullStateScene::class, $registry->get(null));
  }

  /**
   * `get()` on an unregistered state throws `SceneException`.
   */
  public function testGetUnregisteredStateThrowsSceneException(): void
  {
    $registry = new SceneRegistry($this->makeRouter(), registerOnAdd: false);

    $this->expectException(SceneException::class);

    $registry->get('nonexistent_scene');
  }

  // ------------------------------------------------------------------ //
  // Middleware injection: ScenesManager injected when state present
  // ------------------------------------------------------------------ //

  /**
   * The outer middleware injects `'scenes' => ScenesManager` into the data
   * bag when `'state'` is present.
   */
  public function testMiddlewareInjectsScenesManagerWhenStatePresent(): void
  {
    $router = $this->makeRouter();
    new SceneRegistry($router);

    $ctx = $this->makeContext();
    $data = ['state' => $ctx];

    $captured = $this->invokeFirstMiddleware($router, $data);

    self::assertArrayHasKey('scenes', $captured);
    self::assertInstanceOf(ScenesManager::class, $captured['scenes']);
  }

  /**
   * The outer middleware bypasses (delegates) when `'state'` is not in the
   * data bag.
   */
  public function testMiddlewareBypassesWhenStateAbsent(): void
  {
    $router = $this->makeRouter();
    new SceneRegistry($router);

    // No 'state' key in data — middleware should delegate unchanged.
    $data = ['bot' => 'fake_bot'];

    $captured = $this->invokeFirstMiddleware($router, $data);

    self::assertArrayNotHasKey('scenes', $captured);
  }

  // ------------------------------------------------------------------ //
  // Helpers — private
  // ------------------------------------------------------------------ //

  /**
   * Build a `State` object whose `state()` returns the given string.
   *
   * We pass an explicit `$groupName = ''` so the qualified form becomes
   * `':registry_scene_1'` — that wouldn't match. Instead we construct a
   * subclass whose `state()` is overridden to return the bare string, which
   * is the cleanest way to build a test State without StatesGroup bootstrap.
   */
  private function makeStateWithValue(string $value): State
  {
    return new class ($value) extends State {
      public function __construct(private readonly string $val)
      {
        parent::__construct($val);
      }

      public function state(): string
      {
        return $this->val;
      }
    };
  }
}

// ------------------------------------------------------------------ //
// Fixture scene classes used by SceneRegistryTest
// ------------------------------------------------------------------ //

/**
 * Minimal scene fixture with explicit state constant.
 */
final class RegistryScene1 extends Scene
{
  public const string STATE = 'registry_scene_1';

  public static function sceneConfig(): SceneConfig
  {
    return new SceneConfig(state: self::STATE, handlers: [], actions: []);
  }

  public static function sceneState(): string
  {
    return self::STATE;
  }

  // Expose a trivially named router so SceneRegistry::add() has a stable name to assert on.
  public static function asRouter(?string $name = null): Router
  {
    return new Router($name ?? self::STATE);
  }
}

/**
 * Second scene fixture for multi-scene tests.
 */
final class RegistryScene2 extends Scene
{
  public const string STATE = 'registry_scene_2';

  public static function sceneConfig(): SceneConfig
  {
    return new SceneConfig(state: self::STATE, handlers: [], actions: []);
  }

  public static function sceneState(): string
  {
    return self::STATE;
  }

  public static function asRouter(?string $name = null): Router
  {
    return new Router($name ?? self::STATE);
  }
}

/**
 * Scene registered under null state (the no-state sentinel).
 */
final class RegistryNullStateScene extends Scene
{
  public static function sceneConfig(): SceneConfig
  {
    return new SceneConfig(state: null, handlers: [], actions: []);
  }

  public static function sceneState(): ?string
  {
    return null;
  }

  public static function asRouter(?string $name = null): Router
  {
    return new Router($name ?? 'null_state_scene');
  }
}
