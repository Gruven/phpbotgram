<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Scene;

use Gruven\PhpBotGram\Exceptions\SceneException;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\HistoryManager;
use Gruven\PhpBotGram\Fsm\Scene\HistoryManagerInterface;
use Gruven\PhpBotGram\Fsm\Scene\SceneConfig;
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistryInterface;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;
use Gruven\PhpBotGram\Fsm\SceneAction;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Upstream `tests/test_fsm/test_scene.py` `ScenesManager` cases deliberately
 * not ported here:
 *
 * - `TestScenesManager::test_enter_*` async integration cases — dispatcher
 *   integration: require `FSMContext` async calls and full dispatcher loop.
 * - `TestScenesManager::test_leave_*` async cases — same as above.
 * - `TestScenesManager::test_close_*` async cases — same as above.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 */
final class ScenesManagerTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // Helper builders
  // ------------------------------------------------------------------ //

  private function makeStorage(): MemoryStorage
  {
    return new MemoryStorage();
  }

  private function makeKey(): StorageKey
  {
    return new StorageKey(botId: 1, chatId: 100, userId: 42);
  }

  private function makeContext(?MemoryStorage $storage = null): FsmContext
  {
    return new FsmContext(
      storage: $storage ?? $this->makeStorage(),
      key: $this->makeKey(),
    );
  }

  /**
   * Build a fake `SceneRegistryInterface` that maps a state string to a class.
   *
   * @param array<string, class-string<Scene>> $map State → class mappings.
   */
  private function makeRegistry(array $map = []): SceneRegistryInterface
  {
    return new class ($map) implements SceneRegistryInterface {
      /** @param array<string, class-string<Scene>> $map */
      public function __construct(private array $map) {}

      /** @return class-string<Scene> */
      public function get(State|string|null $sceneType): string
      {
        if ($sceneType === null) {
          throw new SceneException('Cannot resolve null scene type.');
        }

        $key = $sceneType instanceof State ? $sceneType->state() : $sceneType;

        if ($key === null || !isset($this->map[$key])) {
          throw new SceneException("Scene not found: {$key}");
        }

        return $this->map[$key];
      }
    };
  }

  private function makeManager(
    ?SceneRegistryInterface $registry = null,
    ?FsmContext $ctx = null,
    string $updateType = 'message',
  ): ScenesManager {
    return new ScenesManager(
      registry: $registry ?? $this->makeRegistry(),
      updateType: $updateType,
      event: new stdClass(),
      state: $ctx ?? $this->makeContext(),
      data: [],
    );
  }

  // ------------------------------------------------------------------ //
  // Constructor / history()
  // ------------------------------------------------------------------ //

  /**
   * `history()` accessor returns a `HistoryManagerInterface` instance.
   */
  public function testHistoryAccessorReturnsHistoryManager(): void
  {
    $manager = $this->makeManager();

    self::assertInstanceOf(HistoryManagerInterface::class, $manager->history());
    self::assertInstanceOf(HistoryManager::class, $manager->history());
  }

  /**
   * The same `HistoryManager` instance is returned on repeated calls.
   */
  public function testHistoryAccessorReturnsSameInstance(): void
  {
    $manager = $this->makeManager();

    self::assertSame($manager->history(), $manager->history());
  }

  // ------------------------------------------------------------------ //
  // enter() — basic path
  // ------------------------------------------------------------------ //

  /**
   * `enter(scene_class)` calls `SceneWizard::enter()` on the resolved scene,
   * which sets the FSM state.
   */
  public function testEnterSetsSceneFsmState(): void
  {
    $ctx = $this->makeContext();
    $registry = $this->makeRegistry([FixtureScene::STATE => FixtureScene::class]);

    $manager = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    $manager->enter(FixtureScene::STATE);

    self::assertSame(FixtureScene::STATE, $ctx->getState());
  }

  /**
   * `enter(scene)` with `$checkActive=true` exits the currently active scene
   * before entering the new one.
   *
   * We verify this by pre-setting the FSM state to the fixture scene's state,
   * then inspecting the `FixtureScene::$exitCalled` flag.
   */
  public function testEnterExitsActiveScenesBeforeEnteringNewOne(): void
  {
    FixtureScene::reset();

    $ctx = $this->makeContext();
    // Simulate the fixture scene already being active.
    $ctx->setState(FixtureScene::STATE);

    $registry = $this->makeRegistry([FixtureScene::STATE => FixtureScene::class]);

    $manager = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    // enter() with checkActive=true (default) should exit the active scene first.
    // The active scene's exit clears the FSM state (via manager.enter(null)).
    // We then re-enter — the fixture records the exit call.
    $manager->enter(FixtureScene::STATE, true);

    self::assertTrue(FixtureScene::$exitCalled, 'exit() must be called on the active scene');
  }

  /**
   * `enter` with `$checkActive=false` skips exiting the active scene.
   */
  public function testEnterSkipsExitWhenCheckActiveFalse(): void
  {
    FixtureScene::reset();

    $ctx = $this->makeContext();
    $ctx->setState(FixtureScene::STATE);

    $registry = $this->makeRegistry([FixtureScene::STATE => FixtureScene::class]);

    $manager = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    $manager->enter(FixtureScene::STATE, false);

    self::assertFalse(FixtureScene::$exitCalled, 'exit() must NOT be called when checkActive=false');
  }

  // ------------------------------------------------------------------ //
  // enter(null) — clear FSM path
  // ------------------------------------------------------------------ //

  /**
   * `enter(null)` when no active scene clears the FSM state.
   */
  public function testEnterNullWithNoActiveSeneClearsFsmState(): void
  {
    $ctx = $this->makeContext();
    $ctx->setState('some_orphan_state');

    // Registry that knows nothing — every lookup throws SceneException.
    $registry = $this->makeRegistry([]);

    $manager = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    $manager->enter(null);

    self::assertNull($ctx->getState());
  }

  /**
   * `enter(unknownScene)` where the registry does not know the scene
   * re-throws the `SceneException` when `$scene` is not `null`.
   */
  public function testEnterUnknownNonNullSceneRethrowsException(): void
  {
    $ctx = $this->makeContext();
    $registry = $this->makeRegistry([]);

    $manager = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    $this->expectException(SceneException::class);

    $manager->enter('nonexistent_scene');
  }

  // ------------------------------------------------------------------ //
  // close()
  // ------------------------------------------------------------------ //

  /**
   * `close()` exits the currently active scene.
   */
  public function testCloseExitsActiveScene(): void
  {
    FixtureScene::reset();

    $ctx = $this->makeContext();
    $ctx->setState(FixtureScene::STATE);

    $registry = $this->makeRegistry([FixtureScene::STATE => FixtureScene::class]);

    $manager = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    $manager->close();

    self::assertTrue(FixtureScene::$exitCalled, 'close() must call exit() on the active scene');
  }

  /**
   * `close()` when no scene is active is a no-op (no exception).
   */
  public function testCloseWithNoActiveSceneIsNoOp(): void
  {
    $ctx = $this->makeContext();
    // FSM state is null — no active scene.
    $registry = $this->makeRegistry([]);

    $manager = new ScenesManager(
      registry: $registry,
      updateType: 'message',
      event: new stdClass(),
      state: $ctx,
      data: [],
    );

    // Must not throw.
    $manager->close();

    $this->addToAssertionCount(1);
  }

  // ------------------------------------------------------------------ //
  // Constructor fields
  // ------------------------------------------------------------------ //

  /**
   * The constructor correctly sets all positional fields (smoke test via history).
   */
  public function testConstructorSetsAllFields(): void
  {
    $storage = $this->makeStorage();
    $ctx = new FsmContext($storage, $this->makeKey());
    $event = new stdClass();
    $registry = $this->makeRegistry([]);

    $manager = new ScenesManager(
      registry: $registry,
      updateType: 'callback_query',
      event: $event,
      state: $ctx,
      data: ['foo' => 'bar'],
    );

    // history() being accessible verifies construction completed.
    self::assertInstanceOf(HistoryManagerInterface::class, $manager->history());
  }
}

// ------------------------------------------------------------------ //
// Fixture scene for ScenesManagerTest
// ------------------------------------------------------------------ //

/**
 * Named concrete `Scene` subclass used as a spy in `ScenesManagerTest`.
 *
 * Records whether `exit()` was called so tests can assert the sequence of
 * lifecycle calls.
 *
 * `sceneConfig()` is overridden to return a `SceneConfig` that wires the
 * fixture's spy handler into the `Exit` action for the `message` update type.
 *
 * Note on action wiring: `SceneWizard::exit()` dispatches the `Exit` lifecycle
 * action via `onAction(SceneAction::Exit)`, which resolves the callable from
 * `sceneConfig->actions[SceneAction::Exit->name][$updateType]`. The spy
 * callable is registered there so the test can observe the dispatch.
 */
final class FixtureScene extends Scene
{
  public const string STATE = 'fixture_scene';

  public static bool $exitCalled = false;

  public static function reset(): void
  {
    self::$exitCalled = false;
  }

  public static function sceneConfig(): SceneConfig
  {
    return new SceneConfig(
      state: self::STATE,
      handlers: [],
      actions: [
        SceneAction::Exit->name => [
          // Register for 'message' update type so the spy fires in tests.
          'message' => static function (): void {
            FixtureScene::$exitCalled = true;
          },
        ],
      ],
    );
  }
}
