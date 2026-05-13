<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Filters\StateFilter;
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\Exception\SceneException;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnCallbackQuery;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnMessage;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use Gruven\PhpBotGram\Fsm\Scene\SceneConfig;
use Gruven\PhpBotGram\Fsm\Scene\SceneHandlerWrapper;
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistryInterface;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;
use Gruven\PhpBotGram\Fsm\SceneAction;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Covers `Scene::asRouter()`, `Scene::addToRouter()`, `Scene::asHandler()`,
 * `Scene::sceneConfig()` reflection builder, and `SceneHandlerWrapper`.
 *
 * Mirrors `Scene.as_router()` / `Scene.as_handler()` / `Scene.add_to_router()`
 * (`aiogram/fsm/scene.py:379-440`).
 *
 * @see Scene
 * @see SceneHandlerWrapper
 */
final class SceneRoutingTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // 1. sceneConfig() reflection builder
  // ------------------------------------------------------------------ //

  /**
   * A scene decorated with `#[SceneState('greet')]` and one `#[OnMessage]`
   * method produces a `SceneConfig` with the correct state and one handler.
   */
  public function testSceneConfigBuildsFromAttributes(): void
  {
    $config = SimpleMessageScene::sceneConfig();

    self::assertSame('greet', $config->state);
    self::assertCount(1, $config->handlers);
    self::assertSame('message', $config->handlers[0]->name);
    self::assertNull($config->handlers[0]->after);
  }

  /**
   * A method decorated with `#[OnMessage(action: SceneAction::Enter)]` is
   * recorded in `actions['Enter']['message']`, NOT in `handlers`.
   */
  public function testActionAttributePopulatesActionsNotHandlers(): void
  {
    $config = WithEnterActionScene::sceneConfig();

    self::assertCount(0, $config->handlers, 'action-decorated method must not appear in handlers');
    self::assertArrayHasKey('Enter', $config->actions);
    self::assertArrayHasKey('message', $config->actions['Enter']);
    self::assertIsCallable($config->actions['Enter']['message']);
  }

  /**
   * `sceneConfig()` is cached: two calls return the identical object.
   */
  public function testSceneConfigIsCachedPerClass(): void
  {
    $a = SimpleMessageScene::sceneConfig();
    $b = SimpleMessageScene::sceneConfig();

    self::assertSame($a, $b, 'sceneConfig() must return the same instance on repeated calls');
  }

  /**
   * Two different scene classes each get their own independently cached config.
   */
  public function testSceneConfigCacheIsPerClass(): void
  {
    $a = SimpleMessageScene::sceneConfig();
    $b = WithEnterActionScene::sceneConfig();

    self::assertNotSame($a, $b);
  }

  // ------------------------------------------------------------------ //
  // 2. asRouter() — router building
  // ------------------------------------------------------------------ //

  /**
   * `asRouter()` returns a `Router` instance.
   */
  public function testAsRouterReturnsRouter(): void
  {
    $router = SimpleMessageScene::asRouter();

    self::assertInstanceOf(Router::class, $router);
  }

  /**
   * The returned router's `message` observer has exactly one handler
   * registered for a scene with a single `#[OnMessage]` method.
   */
  public function testAsRouterRegistersHandlerOnCorrectObserver(): void
  {
    $router = SimpleMessageScene::asRouter();

    self::assertCount(1, $router->observers['message']->handlers);
    self::assertCount(0, $router->observers['callback_query']->handlers);
  }

  /**
   * The router name defaults to "Scene '<FQCN>' for state '<state>'".
   */
  public function testAsRouterDefaultName(): void
  {
    $router = SimpleMessageScene::asRouter();
    $config = SimpleMessageScene::sceneConfig();

    $expected = "Scene '" . SimpleMessageScene::class . "' for state '{$config->state}'";
    self::assertSame($expected, $router->name);
  }

  /**
   * An explicit name is used when provided.
   */
  public function testAsRouterCustomName(): void
  {
    $router = SimpleMessageScene::asRouter('custom_name');

    self::assertSame('custom_name', $router->name);
  }

  /**
   * The `message` observer on the returned router has a `StateFilter` global
   * filter registered.
   */
  public function testAsRouterRegistersStateFilterOnUsedObserver(): void
  {
    $router = SimpleMessageScene::asRouter();

    $observer = $router->observers['message'];
    self::assertCount(1, $observer->filters, 'StateFilter must be registered as a global filter');
  }

  /**
   * Observers that have NO handlers registered do NOT get a StateFilter.
   */
  public function testAsRouterDoesNotRegisterStateFilterOnUnusedObserver(): void
  {
    $router = SimpleMessageScene::asRouter();

    // callback_query has no handlers, so it should have no filters either.
    self::assertCount(0, $router->observers['callback_query']->filters);
  }

  /**
   * A scene with `callbackQueryWithoutState=true` does NOT add a StateFilter
   * on the `callback_query` observer, but DOES add it on other observers.
   */
  public function testCallbackQueryWithoutStateSkipsStateFilter(): void
  {
    $router = CallbackQueryWithoutStateScene::asRouter();

    self::assertCount(1, $router->observers['callback_query']->handlers, 'handler must be registered');
    self::assertCount(0, $router->observers['callback_query']->filters, 'no StateFilter on callback_query');
    // message observer is also used — it DOES get a StateFilter.
    self::assertCount(1, $router->observers['message']->filters, 'StateFilter on message');
  }

  // ------------------------------------------------------------------ //
  // 3. asHandler() — entry-point callable
  // ------------------------------------------------------------------ //

  /**
   * `asHandler()` returns a `Closure`.
   */
  public function testAsHandlerReturnsClosure(): void
  {
    $handler = SimpleMessageScene::asHandler();

    self::assertInstanceOf(Closure::class, $handler);
  }

  /**
   * The handler closure calls `$scenes->enter()` with the scene class when
   * invoked with a `ScenesManager` in kwargs.
   *
   * The spy registry records the class passed to `get()` then throws so
   * we can inspect the argument without fully completing the enter flow.
   */
  public function testAsHandlerCallsScenesEnter(): void
  {
    $entered = [];
    $fakeScenes = $this->makeFakeScenesManager($entered);

    $handler = SimpleMessageScene::asHandler();

    // The spy will throw SceneException with 'spy:' prefix once the class is recorded.
    try {
      $handler(new stdClass(), scenes: $fakeScenes);
    } catch (SceneException $e) {
      // Expected — the spy throws after recording.
      self::assertStringStartsWith('spy:', $e->getMessage());
    }

    self::assertCount(1, $entered, 'scenes->enter() must be called exactly once');
    self::assertSame(SimpleMessageScene::class, $entered[0]);
  }

  /**
   * The handler throws `SceneException` when `$scenes` is absent from kwargs.
   */
  public function testAsHandlerThrowsWhenScenesAbsent(): void
  {
    $this->expectException(SceneException::class);
    $this->expectExceptionMessageMatches('/scenes.*not available/i');

    $handler = SimpleMessageScene::asHandler();
    $handler(new stdClass());
  }

  // ------------------------------------------------------------------ //
  // 4. SceneHandlerWrapper — handler invocation
  // ------------------------------------------------------------------ //

  /**
   * The wrapper instantiates the scene and calls the bound handler method.
   */
  public function testSceneHandlerWrapperCallsHandler(): void
  {
    $called = false;

    $handler = static function (Scene $scene, object $event, mixed ...$kwargs) use (&$called): void {
      $called = true;
    };

    $wrapper = new SceneHandlerWrapper(
      sceneClass: SimpleMessageScene::class,
      handler: $handler,
    );

    $event = new stdClass();
    [$scenes, $ctx] = $this->makeScenesAndCtx();

    $wrapper($event, ...['state' => $ctx, 'scenes' => $scenes]);

    self::assertTrue($called);
  }

  /**
   * The scene instance passed to the handler is of the correct class.
   */
  public function testSceneHandlerWrapperPassesCorrectSceneInstance(): void
  {
    $receivedScene = null;

    $handler = static function (Scene $scene, object $event, mixed ...$kwargs) use (&$receivedScene): void {
      $receivedScene = $scene;
    };

    $wrapper = new SceneHandlerWrapper(
      sceneClass: SimpleMessageScene::class,
      handler: $handler,
    );

    [$scenes, $ctx] = $this->makeScenesAndCtx();
    $wrapper(new stdClass(), ...['state' => $ctx, 'scenes' => $scenes]);

    self::assertInstanceOf(SimpleMessageScene::class, $receivedScene);
  }

  /**
   * The wrapper throws `SceneException` when `'state'` is absent from kwargs.
   */
  public function testSceneHandlerWrapperThrowsWhenStateAbsent(): void
  {
    $this->expectException(SceneException::class);
    $this->expectExceptionMessageMatches('/state.*not available/i');

    $wrapper = new SceneHandlerWrapper(
      sceneClass: SimpleMessageScene::class,
      handler: static function (Scene $scene, object $event): void {},
    );

    $wrapper(new stdClass());
  }

  /**
   * The wrapper throws `SceneException` when `'scenes'` is absent from kwargs.
   */
  public function testSceneHandlerWrapperThrowsWhenScenesAbsent(): void
  {
    $this->expectException(SceneException::class);
    $this->expectExceptionMessageMatches('/scenes.*not available/i');

    $wrapper = new SceneHandlerWrapper(
      sceneClass: SimpleMessageScene::class,
      handler: static function (Scene $scene, object $event): void {},
    );

    $ctx = $this->makeFsmContext();
    $wrapper(new stdClass(), ...['state' => $ctx]);
  }

  /**
   * When an `After::exit()` is attached the wizard `exit()` method is called
   * after the handler returns.
   */
  public function testSceneHandlerWrapperExecutesAfterExit(): void
  {
    $exitCalled = false;

    // We need a spy scene whose wizard's exit() we can observe.
    // Build a ScenesManager + FsmContext that records whether exit was triggered.
    $ctx = $this->makeFsmContext();
    // Pre-set state so we can detect clearing.
    $ctx->setState(SimpleMessageScene::sceneConfig()->state);

    // Spy handler — no-op.
    $handler = static function (Scene $scene, object $event, mixed ...$kwargs): void {};

    $wrapper = new SceneHandlerWrapper(
      sceneClass: SimpleMessageScene::class,
      handler: $handler,
      after: After::exit(),
    );

    $registry = $this->makeMinimalRegistry(SimpleMessageScene::class);
    $scenes = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    $wrapper(new stdClass(), ...['state' => $ctx, 'scenes' => $scenes]);

    // After::exit() calls wizard->exit() → manager->enter(null) → clears FSM state.
    self::assertNull($ctx->getState(), 'After::exit() must clear the FSM state');
  }

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  private function makeFsmContext(): FsmContext
  {
    return new FsmContext(
      new MemoryStorage(),
      new StorageKey(botId: 1, chatId: 1, userId: 1),
    );
  }

  /**
   * @return array{ScenesManager, FsmContext}
   */
  private function makeScenesAndCtx(): array
  {
    $ctx = $this->makeFsmContext();
    $registry = $this->makeMinimalRegistry(SimpleMessageScene::class);

    $scenes = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    return [$scenes, $ctx];
  }

  /**
   * Build a minimal `SceneRegistryInterface` that resolves the given scene class
   * by its state, and throws `SceneException` for `null` (triggering the
   * "clear FSM state" path in `ScenesManager::enter(null)`).
   *
   * @param class-string<Scene> $sceneClass
   *
   * @return SceneRegistryInterface
   */
  private function makeMinimalRegistry(string $sceneClass): SceneRegistryInterface
  {
    return new class ($sceneClass) implements SceneRegistryInterface {
      /** @param class-string<Scene> $sceneClass */
      public function __construct(private readonly string $sceneClass) {}

      /** @return class-string<Scene> */
      public function get(null|State|string $sceneType): string
      {
        if ($sceneType === null) {
          throw new SceneException('null scene — clear state');
        }

        return $this->sceneClass;
      }
    };
  }

  /**
   * Build a fake `ScenesManager` spy that records `enter()` calls.
   *
   * @param list<string> $entered Reference to a list that records entered scene classes.
   *
   * @return ScenesManager
   */
  private function makeFakeScenesManager(array &$entered): ScenesManager
  {
    $ctx = $this->makeFsmContext();

    // Use a Closure that captures $entered by reference so the spy can push
    // to the outer array without PHPStan "property never read" complaints.
    $push = static function (string $value) use (&$entered): void {
      $entered[] = $value;
    };

    $registry = new class ($push) implements SceneRegistryInterface {
      /** @param Closure(string): void $push */
      public function __construct(private readonly Closure $push) {}

      /** @return class-string<Scene> */
      public function get(null|State|string $sceneType): string
      {
        if ($sceneType === null) {
          // Let ScenesManager take the "clear state" path.
          throw new SceneException('spy: null scene clears state');
        }

        ($this->push)($sceneType instanceof State ? $sceneType::class : $sceneType);

        // Throw so ScenesManager does not actually instantiate the scene.
        throw new SceneException('spy: stop after recording');
      }
    };

    return new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );
  }
}

// ------------------------------------------------------------------ //
// Fixture scene classes
// ------------------------------------------------------------------ //

/**
 * A scene with `#[SceneState('greet')]` and one `#[OnMessage]` handler.
 * Used to test the basic asRouter() / sceneConfig() flow.
 */
#[SceneState('greet')]
final class SimpleMessageScene extends Scene
{
  public bool $handlerCalled = false;

  #[OnMessage]
  public function onMessage(object $event, mixed ...$kwargs): void
  {
    $this->handlerCalled = true;
  }
}

/**
 * A scene whose `#[OnMessage(action: SceneAction::Enter)]` method registers
 * as a lifecycle action, not an ordinary handler.
 */
#[SceneState('with_enter_action')]
final class WithEnterActionScene extends Scene
{
  #[OnMessage(action: SceneAction::Enter)]
  public function onEnter(object $event, mixed ...$kwargs): void {}
}

/**
 * A scene that uses `callbackQueryWithoutState=true`, meaning the
 * `callback_query` observer must NOT receive a StateFilter.
 * It has both a message handler (for the StateFilter-must-be-added path)
 * and a callback_query handler (for the skip path).
 */
#[SceneState('cbq_without_state')]
final class CallbackQueryWithoutStateScene extends Scene
{
  #[OnMessage]
  public function onMessage(object $event, mixed ...$kwargs): void {}

  #[OnCallbackQuery]
  public function onCallbackQuery(object $event, mixed ...$kwargs): void {}

  public static function sceneConfig(): SceneConfig
  {
    // Build the config from reflection but override callbackQueryWithoutState.
    $base = parent::sceneConfig();

    return new SceneConfig(
      state: $base->state,
      handlers: $base->handlers,
      actions: $base->actions,
      resetDataOnEnter: $base->resetDataOnEnter,
      resetHistoryOnEnter: $base->resetHistoryOnEnter,
      callbackQueryWithoutState: true,
    );
  }
}
