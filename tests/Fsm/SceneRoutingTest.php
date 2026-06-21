<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Exceptions\SceneException;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\StateFilter;
use Gruven\PhpBotGram\Fsm\After;
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
 * Upstream `tests/test_fsm/test_scene.py` routing and config cases
 * deliberately not ported here:
 *
 * - `TestSceneConfig::*` async registration / dispatcher integration cases —
 *   dispatcher integration: require a full `Dispatcher.feed_update()` run
 *   and async FSM context.
 * - `test_scene.py::TestScene::test_scene_with_*` dispatcher-integration
 *   cases — dispatcher integration: require end-to-end message routing.
 * - `TestSceneHandlerWrapper::test_await` — API divergence: PHP has no
 *   `__await__` protocol; `SceneHandlerWrapper` is not awaitable in PHP.
 * - `TestSceneHandlerWrapper::test_scene_handler_wrapper_str` — API divergence:
 *   PHP does not guarantee `__toString` parity with Python `__str__`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
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

  public function testActiveSceneRouterRunsBeforeParentCatchAllHandler(): void
  {
    StrictMessageScene::$handlerCalled = false;
    $rootHandlerCalled = false;

    $root = new Router('root');
    $root->includeRouter(StrictMessageScene::asRouter());
    $root->message->register(static function () use (&$rootHandlerCalled): string {
      $rootHandlerCalled = true;

      return 'root';
    });

    [$scenes, $ctx] = $this->makeScenesAndCtx(StrictMessageScene::class);
    $ctx->setState(StrictMessageScene::sceneConfig()->state);

    $result = $root->propagateEvent('message', new stdClass(), [
      'state' => $ctx,
      'scenes' => $scenes,
      'raw_state' => StrictMessageScene::sceneConfig()->state,
    ]);

    self::assertNull($result);
    self::assertTrue(StrictMessageScene::$handlerCalled);
    self::assertFalse($rootHandlerCalled);
  }

  public function testNestedActiveSceneRouterRunsBeforeParentCatchAllHandler(): void
  {
    StrictMessageScene::$handlerCalled = false;
    $rootHandlerCalled = false;
    $featureHandlerCalled = false;

    $root = new Router('root');
    $feature = new Router('feature');
    $feature->includeRouter(StrictMessageScene::asRouter());
    $feature->message->register(static function () use (&$featureHandlerCalled): string {
      $featureHandlerCalled = true;

      return 'feature';
    });
    $root->includeRouter($feature);
    $root->message->register(static function () use (&$rootHandlerCalled): string {
      $rootHandlerCalled = true;

      return 'root';
    });

    [$scenes, $ctx] = $this->makeScenesAndCtx(StrictMessageScene::class);
    $ctx->setState(StrictMessageScene::sceneConfig()->state);

    $result = $root->propagateEvent('message', new stdClass(), [
      'state' => $ctx,
      'scenes' => $scenes,
      'raw_state' => StrictMessageScene::sceneConfig()->state,
    ]);

    self::assertNull($result);
    self::assertTrue(StrictMessageScene::$handlerCalled);
    self::assertFalse($rootHandlerCalled);
    self::assertFalse($featureHandlerCalled);
  }

  public function testNestedScenePrioritySkipsNonMatchingSceneSubtreeCatchAll(): void
  {
    StrictMessageScene::$handlerCalled = false;
    SecondStrictMessageScene::$handlerCalled = false;
    $featureAHandlerCalled = false;
    $featureBHandlerCalled = false;

    $root = new Router('root');

    $featureA = new Router('feature-a');
    $featureA->includeRouter(StrictMessageScene::asRouter());
    $featureA->message->register(static function () use (&$featureAHandlerCalled): string {
      $featureAHandlerCalled = true;

      return 'feature-a';
    });

    $featureB = new Router('feature-b');
    $featureB->includeRouter(SecondStrictMessageScene::asRouter());
    $featureB->message->register(static function () use (&$featureBHandlerCalled): string {
      $featureBHandlerCalled = true;

      return 'feature-b';
    });

    $root->includeRouter($featureA);
    $root->includeRouter($featureB);

    [$scenes, $ctx] = $this->makeScenesAndCtx(SecondStrictMessageScene::class);
    $ctx->setState(SecondStrictMessageScene::sceneConfig()->state);

    $result = $root->propagateEvent('message', new stdClass(), [
      'state' => $ctx,
      'scenes' => $scenes,
      'raw_state' => SecondStrictMessageScene::sceneConfig()->state,
    ]);

    self::assertNull($result);
    self::assertFalse(StrictMessageScene::$handlerCalled);
    self::assertTrue(SecondStrictMessageScene::$handlerCalled);
    self::assertFalse($featureAHandlerCalled);
    self::assertFalse($featureBHandlerCalled);
  }

  public function testNestedScenePrioritySubtreeRunsOuterMiddlewareOnceWhenSceneUnhandled(): void
  {
    StrictMessageScene::$handlerCalled = false;
    $featureHandlerCalled = false;
    $featureOuterCalls = 0;

    $root = new Router('root');
    $feature = new Router('feature');
    $feature->includeRouter(StrictMessageScene::asRouter());
    $feature->message->outerMiddleware(new class ($featureOuterCalls) extends BaseMiddleware {
      public function __construct(private int &$calls) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        ++$this->calls;

        return $handler($event, $data);
      }
    });
    $feature->message->register(static function () use (&$featureHandlerCalled): string {
      $featureHandlerCalled = true;

      return 'feature';
    });
    $root->includeRouter($feature);

    [$scenes, $ctx] = $this->makeScenesAndCtx(StrictMessageScene::class);
    $ctx->setState('other_active_state');

    $result = $root->propagateEvent('message', new stdClass(), [
      'state' => $ctx,
      'scenes' => $scenes,
      'raw_state' => 'other_active_state',
    ]);

    self::assertSame('feature', $result);
    self::assertFalse(StrictMessageScene::$handlerCalled);
    self::assertTrue($featureHandlerCalled);
    self::assertSame(1, $featureOuterCalls);
  }

  public function testNestedScenePriorityMatchingSubtreeRunsOuterMiddlewareOnceWhenSceneHandlerFilterRejects(): void
  {
    FilterRejectingMessageScene::$handlerCalled = false;
    $featureHandlerCalled = false;
    $featureOuterCalls = 0;

    $root = new Router('root');
    $feature = new Router('feature');
    $feature->includeRouter(FilterRejectingMessageScene::asRouter());
    $feature->message->outerMiddleware(new class ($featureOuterCalls) extends BaseMiddleware {
      public function __construct(private int &$calls) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        ++$this->calls;

        return $handler($event, $data);
      }
    });
    $feature->message->register(static function () use (&$featureHandlerCalled): string {
      $featureHandlerCalled = true;

      return 'feature';
    });
    $root->includeRouter($feature);

    [$scenes, $ctx] = $this->makeScenesAndCtx(FilterRejectingMessageScene::class);
    $ctx->setState(FilterRejectingMessageScene::sceneConfig()->state);

    $result = $root->propagateEvent('message', new stdClass(), [
      'state' => $ctx,
      'scenes' => $scenes,
      'raw_state' => FilterRejectingMessageScene::sceneConfig()->state,
    ]);

    self::assertSame('feature', $result);
    self::assertFalse(FilterRejectingMessageScene::$handlerCalled);
    self::assertTrue($featureHandlerCalled);
    self::assertSame(1, $featureOuterCalls);
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

  /**
   * `asHandler(checkActive: false)` passes `false` positionally to
   * `ScenesManager::enter`, so the active-scene-exit step is skipped.
   *
   * Regression: before the fix, `checkActive` had no dedicated parameter;
   * passing it as a named kwarg would either be silently ignored (wrong
   * semantics) or cause a PHP duplicate-named-arg Error (crash).
   */
  public function testAsHandlerAcceptsExplicitCheckActiveFalse(): void
  {
    $entered = [];
    $fakeScenes = $this->makeFakeScenesManager($entered);

    // Pass checkActive: false via the dedicated top-level parameter.
    $handler = SimpleMessageScene::asHandler(checkActive: false);

    try {
      $handler(new stdClass(), scenes: $fakeScenes);
    } catch (SceneException $e) {
      // The spy registry throws after recording — expected.
      self::assertStringStartsWith('spy:', $e->getMessage());
    }

    // The scene must still be entered (checkActive only affects exit, not entry).
    self::assertCount(1, $entered, 'scenes->enter() must be called exactly once');
    self::assertSame(SimpleMessageScene::class, $entered[0]);
  }

  /**
   * When the middleware bag contains a `checkActive` key, no Error is thrown.
   *
   * Regression: before the fix, a kwarg named `checkActive` from the
   * middleware bag would duplicate-bind to `ScenesManager::enter`'s
   * `$checkActive` parameter and PHP would throw an Error (duplicate named
   * argument). The defensive `unset($mergedKwargs['checkActive'])` silently
   * strips it so the explicit `$checkActive` value always wins.
   */
  public function testAsHandlerSilentlyStripsCheckActiveFromKwargBag(): void
  {
    $entered = [];
    $fakeScenes = $this->makeFakeScenesManager($entered);

    $handler = SimpleMessageScene::asHandler();

    // Inject `checkActive` into the middleware bag — must NOT throw an Error.
    try {
      $handler(new stdClass(), scenes: $fakeScenes, checkActive: false);
    } catch (SceneException $e) {
      // The spy registry throws after recording — expected.
      self::assertStringStartsWith('spy:', $e->getMessage());
    }

    // Scene must still be entered despite the checkActive kwarg collision.
    self::assertCount(1, $entered, 'scenes->enter() must be called exactly once');
  }

  /**
   * When the middleware bag contains a `scene` key, no Error is thrown.
   *
   * Regression: `ScenesManager::enter`'s first positional parameter is `$scene`.
   * A kwarg named `scene` in the merged bag would duplicate-bind and PHP would
   * throw "Cannot use positional argument after named argument during unpacking"
   * (or a duplicate-named-arg Error). The defensive `unset($mergedKwargs['scene'])`
   * silently strips it before the spread so `enter($sceneClass, $checkActive, ...)`
   * never sees a collision.
   */
  public function testAsHandlerSilentlyStripsSceneFromKwargBag(): void
  {
    $entered = [];
    $fakeScenes = $this->makeFakeScenesManager($entered);

    $handler = SimpleMessageScene::asHandler();

    // Inject `scene` into the middleware bag — must NOT throw an Error.
    try {
      $handler(new stdClass(), scenes: $fakeScenes, scene: 'some_scene_value');
    } catch (SceneException $e) {
      // The spy registry throws after recording — expected.
      self::assertStringStartsWith('spy:', $e->getMessage());
    }

    // Scene must still be entered despite the scene kwarg collision.
    self::assertCount(1, $entered, 'scenes->enter() must be called exactly once');
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

  public function testSceneHandlerWrapperFiltersWorkflowKwargsForStrictSceneMethods(): void
  {
    StrictMessageScene::$handlerCalled = false;

    $handler = StrictMessageScene::sceneConfig()->handlers[0]->handler;
    $wrapper = new SceneHandlerWrapper(
      sceneClass: StrictMessageScene::class,
      handler: $handler,
    );

    [$scenes, $ctx] = $this->makeScenesAndCtx(StrictMessageScene::class);

    $wrapper(new stdClass(), ...[
      'state' => $ctx,
      'scenes' => $scenes,
      'demo_storage' => new stdClass(),
    ]);

    self::assertTrue(StrictMessageScene::$handlerCalled);
  }

  public function testSceneHandlerWrapperTreatsObjectMessageAsEvent(): void
  {
    ObjectMessageScene::$handlerCalled = false;
    ObjectMessageScene::$message = null;

    $handler = ObjectMessageScene::sceneConfig()->handlers[0]->handler;
    $wrapper = new SceneHandlerWrapper(
      sceneClass: ObjectMessageScene::class,
      handler: $handler,
    );

    [$scenes, $ctx] = $this->makeScenesAndCtx(ObjectMessageScene::class);
    $event = new stdClass();

    $wrapper($event, ...[
      'state' => $ctx,
      'scenes' => $scenes,
      'demo_storage' => new stdClass(),
    ]);

    self::assertTrue(ObjectMessageScene::$handlerCalled);
    self::assertSame($event, ObjectMessageScene::$message);
  }

  public function testSceneHandlerWrapperPreservesDeclaredWorkflowKwargs(): void
  {
    WorkflowKwargsScene::$handlerCalled = false;
    WorkflowKwargsScene::$state = null;
    WorkflowKwargsScene::$demoStorage = null;

    $handler = WorkflowKwargsScene::sceneConfig()->handlers[0]->handler;
    $wrapper = new SceneHandlerWrapper(
      sceneClass: WorkflowKwargsScene::class,
      handler: $handler,
    );

    [$scenes, $ctx] = $this->makeScenesAndCtx(WorkflowKwargsScene::class);
    $demoStorage = new stdClass();

    $wrapper(new stdClass(), ...[
      'state' => $ctx,
      'scenes' => $scenes,
      'demo_storage' => $demoStorage,
      'ignored' => new stdClass(),
    ]);

    self::assertTrue(WorkflowKwargsScene::$handlerCalled);
    self::assertSame($ctx, WorkflowKwargsScene::$state);
    self::assertSame($demoStorage, WorkflowKwargsScene::$demoStorage);
  }

  public function testSceneHandlerWrapperSupportsWorkflowFirstSceneMethods(): void
  {
    WorkflowFirstScene::$handlerCalled = false;
    WorkflowFirstScene::$rawState = null;
    WorkflowFirstScene::$state = null;

    $handler = WorkflowFirstScene::sceneConfig()->handlers[0]->handler;
    $wrapper = new SceneHandlerWrapper(
      sceneClass: WorkflowFirstScene::class,
      handler: $handler,
    );

    [$scenes, $ctx] = $this->makeScenesAndCtx(WorkflowFirstScene::class);

    $wrapper(new stdClass(), ...[
      'state' => $ctx,
      'scenes' => $scenes,
      'raw_state' => 'workflow_first',
      'ignored' => new stdClass(),
    ]);

    self::assertTrue(WorkflowFirstScene::$handlerCalled);
    self::assertSame('workflow_first', WorkflowFirstScene::$rawState);
    self::assertSame($ctx, WorkflowFirstScene::$state);
  }

  public function testSceneHandlerWrapperSupportsVariadicOnlySceneMethods(): void
  {
    VariadicOnlyScene::$handlerCalled = false;
    VariadicOnlyScene::$eventWasForwarded = false;
    VariadicOnlyScene::$state = null;
    VariadicOnlyScene::$demoStorage = null;

    $handler = VariadicOnlyScene::sceneConfig()->handlers[0]->handler;
    $wrapper = new SceneHandlerWrapper(
      sceneClass: VariadicOnlyScene::class,
      handler: $handler,
    );

    [$scenes, $ctx] = $this->makeScenesAndCtx(VariadicOnlyScene::class);
    $demoStorage = new stdClass();

    $wrapper(new stdClass(), ...[
      'state' => $ctx,
      'scenes' => $scenes,
      'demo_storage' => $demoStorage,
    ]);

    self::assertTrue(VariadicOnlyScene::$handlerCalled);
    self::assertTrue(VariadicOnlyScene::$eventWasForwarded);
    self::assertSame($ctx, VariadicOnlyScene::$state);
    self::assertSame($demoStorage, VariadicOnlyScene::$demoStorage);
  }

  public function testSceneLifecycleActionFiltersWorkflowKwargsForStrictSceneMethods(): void
  {
    StrictEnterActionScene::$enterCalled = false;

    [$scenes] = $this->makeScenesAndCtx(StrictEnterActionScene::class);

    $scenes->enter(StrictEnterActionScene::class, demo_storage: new stdClass());

    self::assertTrue(StrictEnterActionScene::$enterCalled);
  }

  public function testSceneLifecycleActionTreatsObjectMessageAsEvent(): void
  {
    ObjectMessageEnterActionScene::$enterCalled = false;
    ObjectMessageEnterActionScene::$message = null;

    [$scenes] = $this->makeScenesAndCtx(ObjectMessageEnterActionScene::class);

    $scenes->enter(ObjectMessageEnterActionScene::class, demo_storage: new stdClass());

    self::assertTrue(ObjectMessageEnterActionScene::$enterCalled);
    self::assertInstanceOf(stdClass::class, ObjectMessageEnterActionScene::$message);
  }

  public function testSceneLifecycleActionPreservesDeclaredWorkflowKwargs(): void
  {
    WorkflowEnterActionScene::$enterCalled = false;
    WorkflowEnterActionScene::$state = null;
    WorkflowEnterActionScene::$demoStorage = null;

    [$scenes, $ctx] = $this->makeScenesAndCtx(WorkflowEnterActionScene::class);
    $demoStorage = new stdClass();

    $scenes->enter(
      WorkflowEnterActionScene::class,
      state: $ctx,
      demo_storage: $demoStorage,
      ignored: new stdClass(),
    );

    self::assertTrue(WorkflowEnterActionScene::$enterCalled);
    self::assertSame($ctx, WorkflowEnterActionScene::$state);
    self::assertSame($demoStorage, WorkflowEnterActionScene::$demoStorage);
  }

  public function testSceneLifecycleActionSupportsWorkflowFirstSceneMethods(): void
  {
    WorkflowFirstEnterActionScene::$enterCalled = false;
    WorkflowFirstEnterActionScene::$rawState = null;
    WorkflowFirstEnterActionScene::$state = null;

    [$scenes, $ctx] = $this->makeScenesAndCtx(WorkflowFirstEnterActionScene::class);

    $scenes->enter(
      WorkflowFirstEnterActionScene::class,
      state: $ctx,
      raw_state: 'workflow_first_enter',
      ignored: new stdClass(),
    );

    self::assertTrue(WorkflowFirstEnterActionScene::$enterCalled);
    self::assertSame('workflow_first_enter', WorkflowFirstEnterActionScene::$rawState);
    self::assertSame($ctx, WorkflowFirstEnterActionScene::$state);
  }

  public function testSceneLifecycleActionSupportsVariadicOnlySceneMethods(): void
  {
    VariadicOnlyEnterActionScene::$enterCalled = false;
    VariadicOnlyEnterActionScene::$eventWasForwarded = false;
    VariadicOnlyEnterActionScene::$state = null;
    VariadicOnlyEnterActionScene::$demoStorage = null;

    [$scenes, $ctx] = $this->makeScenesAndCtx(VariadicOnlyEnterActionScene::class);
    $demoStorage = new stdClass();

    $scenes->enter(
      VariadicOnlyEnterActionScene::class,
      state: $ctx,
      demo_storage: $demoStorage,
    );

    self::assertTrue(VariadicOnlyEnterActionScene::$enterCalled);
    self::assertTrue(VariadicOnlyEnterActionScene::$eventWasForwarded);
    self::assertSame($ctx, VariadicOnlyEnterActionScene::$state);
    self::assertSame($demoStorage, VariadicOnlyEnterActionScene::$demoStorage);
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

  /**
   * When an `After::goto(null)` is attached the wizard silently no-ops
   * (upstream parity: `ActionContainer.execute` guards `target is not None`).
   *
   * Regression for: `After::goto(null)` must NOT call `$wizard->goto('')`
   * which would throw `SceneException("Scene '' is not registered")`.
   */
  public function testSceneHandlerWrapperSkipsAfterEnterWhenSceneIsNull(): void
  {
    $handlerCalled = false;

    $handler = static function (Scene $scene, object $event, mixed ...$kwargs) use (&$handlerCalled): void {
      $handlerCalled = true;
    };

    $wrapper = new SceneHandlerWrapper(
      sceneClass: SimpleMessageScene::class,
      handler: $handler,
      after: After::goto(null),
    );

    [$scenes, $ctx] = $this->makeScenesAndCtx();

    // Must not throw — the null-target Enter action is a silent no-op.
    $wrapper(new stdClass(), ...['state' => $ctx, 'scenes' => $scenes]);

    self::assertTrue($handlerCalled, 'handler must be called even when after is goto(null)');
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
   * @param class-string<Scene> $sceneClass
   *
   * @return array{ScenesManager, FsmContext}
   */
  private function makeScenesAndCtx(string $sceneClass = SimpleMessageScene::class): array
  {
    $ctx = $this->makeFsmContext();
    $registry = $this->makeMinimalRegistry($sceneClass);

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
      public function get(State|string|null $sceneType): string
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
      public function get(State|string|null $sceneType): string
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
 * A scene with a strict handler signature. Workflow-data kwargs must not be
 * forwarded as named arguments to this method.
 */
#[SceneState('strict_message')]
final class StrictMessageScene extends Scene
{
  public static bool $handlerCalled = false;

  #[OnMessage]
  public function onMessage(object $event): void
  {
    self::$handlerCalled = true;
  }
}

#[SceneState('second_strict_message')]
final class SecondStrictMessageScene extends Scene
{
  public static bool $handlerCalled = false;

  #[OnMessage]
  public function onMessage(object $event): void
  {
    self::$handlerCalled = true;
  }
}

#[SceneState('object_message')]
final class ObjectMessageScene extends Scene
{
  public static bool $handlerCalled = false;
  public static ?object $message = null;

  #[OnMessage]
  public function onMessage(object $message): void
  {
    self::$handlerCalled = true;
    self::$message = $message;
  }
}

#[SceneState('filter_rejecting_message')]
final class FilterRejectingMessageScene extends Scene
{
  public static bool $handlerCalled = false;

  #[OnMessage(filters: new SceneRoutingRejectingFilter())]
  public function onMessage(object $event): void
  {
    self::$handlerCalled = true;
  }
}

/**
 * A scene that declares workflow-data parameters. The binding layer must keep
 * matching kwargs while dropping unrelated workflow keys.
 */
#[SceneState('workflow_kwargs')]
final class WorkflowKwargsScene extends Scene
{
  public static bool $handlerCalled = false;
  public static ?FsmContext $state = null;
  public static ?object $demoStorage = null;

  #[OnMessage]
  public function onMessage(object $event, FsmContext $state, object $demo_storage): void
  {
    self::$handlerCalled = true;
    self::$state = $state;
    self::$demoStorage = $demo_storage;
  }
}

#[SceneState('workflow_first')]
final class WorkflowFirstScene extends Scene
{
  public static bool $handlerCalled = false;
  public static ?string $rawState = null;
  public static ?FsmContext $state = null;

  #[OnMessage]
  public function onMessage(?string $raw_state, FsmContext $state): void
  {
    self::$handlerCalled = true;
    self::$rawState = $raw_state;
    self::$state = $state;
  }
}

#[SceneState('variadic_only')]
final class VariadicOnlyScene extends Scene
{
  public static bool $handlerCalled = false;
  public static bool $eventWasForwarded = false;
  public static ?FsmContext $state = null;
  public static ?object $demoStorage = null;

  #[OnMessage]
  public function onMessage(mixed ...$kwargs): void
  {
    self::$handlerCalled = true;
    self::$eventWasForwarded = array_key_exists('event', $kwargs);
    $state = $kwargs['state'] ?? null;
    $demoStorage = $kwargs['demo_storage'] ?? null;
    self::$state = $state instanceof FsmContext ? $state : null;
    self::$demoStorage = is_object($demoStorage) ? $demoStorage : null;
  }
}

/**
 * A scene with a strict lifecycle action signature. Enter handlers should work
 * even when dispatcher workflow data contains unrelated keys.
 */
#[SceneState('strict_enter_action')]
final class StrictEnterActionScene extends Scene
{
  public static bool $enterCalled = false;

  #[OnMessage(action: SceneAction::Enter)]
  public function onEnter(object $event): void
  {
    self::$enterCalled = true;
  }
}

#[SceneState('object_message_enter_action')]
final class ObjectMessageEnterActionScene extends Scene
{
  public static bool $enterCalled = false;
  public static ?object $message = null;

  #[OnMessage(action: SceneAction::Enter)]
  public function onEnter(object $message): void
  {
    self::$enterCalled = true;
    self::$message = $message;
  }
}

/**
 * A lifecycle action that declares workflow-data parameters.
 */
#[SceneState('workflow_enter_action')]
final class WorkflowEnterActionScene extends Scene
{
  public static bool $enterCalled = false;
  public static ?FsmContext $state = null;
  public static ?object $demoStorage = null;

  #[OnMessage(action: SceneAction::Enter)]
  public function onEnter(object $event, FsmContext $state, object $demo_storage): void
  {
    self::$enterCalled = true;
    self::$state = $state;
    self::$demoStorage = $demo_storage;
  }
}

#[SceneState('workflow_first_enter_action')]
final class WorkflowFirstEnterActionScene extends Scene
{
  public static bool $enterCalled = false;
  public static ?string $rawState = null;
  public static ?FsmContext $state = null;

  #[OnMessage(action: SceneAction::Enter)]
  public function onEnter(?string $raw_state, FsmContext $state): void
  {
    self::$enterCalled = true;
    self::$rawState = $raw_state;
    self::$state = $state;
  }
}

#[SceneState('variadic_only_enter_action')]
final class VariadicOnlyEnterActionScene extends Scene
{
  public static bool $enterCalled = false;
  public static bool $eventWasForwarded = false;
  public static ?FsmContext $state = null;
  public static ?object $demoStorage = null;

  #[OnMessage(action: SceneAction::Enter)]
  public function onEnter(mixed ...$kwargs): void
  {
    self::$enterCalled = true;
    self::$eventWasForwarded = array_key_exists('event', $kwargs);
    $state = $kwargs['state'] ?? null;
    $demoStorage = $kwargs['demo_storage'] ?? null;
    self::$state = $state instanceof FsmContext ? $state : null;
    self::$demoStorage = is_object($demoStorage) ? $demoStorage : null;
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

final class SceneRoutingRejectingFilter extends Filter
{
  public function __invoke(object $event, mixed ...$kwargs): bool
  {
    return false;
  }
}
