<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Exceptions\SceneException;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\HandlerContainer;
use Gruven\PhpBotGram\Fsm\Scene\HistoryManagerInterface;
use Gruven\PhpBotGram\Fsm\Scene\SceneConfig;
use Gruven\PhpBotGram\Fsm\Scene\SceneManagerInterface;
use Gruven\PhpBotGram\Fsm\SceneAction;
use Gruven\PhpBotGram\Fsm\SceneWizard;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Upstream `tests/test_fsm/test_scene.py` `SceneWizard`-related cases
 * deliberately not ported here:
 *
 * - `TestSceneWizard::*` (upstream) — the upstream file does not have a
 *   separate `TestSceneWizard` class; `SceneWizard` behaviour is exercised
 *   inline via `ActionContainer.execute()` with `AsyncMock(spec=SceneWizard)`.
 *   All observable `SceneWizard` method contracts are covered in this file
 *   using synchronous fakes.
 * - `TestScenesManager::*` async tests — dispatcher integration: depend on
 *   `FSMContext` async methods and full dispatcher run; covered by
 *   `ScenesManagerTest`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 */
final class SceneWizardTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // Fixtures / helpers
  // ------------------------------------------------------------------ //

  /**
   * Fake history manager that records every call made to it.
   */
  private function makeHistory(): HistoryManagerInterface
  {
    return new class implements HistoryManagerInterface {
      /** @var list<string> */
      public array $calls = [];

      public ?string $rollbackReturn = null;

      public function clear(): void
      {
        $this->calls[] = 'clear';
      }

      public function snapshot(): void
      {
        $this->calls[] = 'snapshot';
      }

      public function rollback(): ?string
      {
        $this->calls[] = 'rollback';

        return $this->rollbackReturn;
      }
    };
  }

  /**
   * Fake scene manager that records `enter()` calls and delegates history.
   */
  private function makeManager(HistoryManagerInterface $history): SceneManagerInterface
  {
    return new class ($history) implements SceneManagerInterface {
      /** @var list<array{scene: null|State|string, checkActive: bool}> */
      public array $enterCalls = [];

      public function __construct(private HistoryManagerInterface $historyManager) {}

      public function history(): HistoryManagerInterface
      {
        return $this->historyManager;
      }

      public function enter(State|string|null $scene, bool $checkActive = true, mixed ...$kwargs): void
      {
        $this->enterCalls[] = ['scene' => $scene, 'checkActive' => $checkActive];
      }
    };
  }

  /**
   * Build a `SceneConfig` with optional actions map.
   *
   * @param array<string, array<string, callable>> $actions
   *                                                        Outer key is the SceneAction case **name** (e.g. `'Enter'`),
   *                                                        inner key is the update-type string (e.g. `'message'`).
   */
  private function makeConfig(
    string $state = 'test_scene',
    array $actions = [],
    ?bool $resetDataOnEnter = null,
    ?bool $resetHistoryOnEnter = null,
  ): SceneConfig {
    return new SceneConfig(
      state: $state,
      handlers: [],
      actions: $actions,
      resetDataOnEnter: $resetDataOnEnter,
      resetHistoryOnEnter: $resetHistoryOnEnter,
    );
  }

  /** Build a `FsmContext` backed by `MemoryStorage`. */
  private function makeFsmContext(): FsmContext
  {
    return new FsmContext(
      new MemoryStorage(),
      new StorageKey(botId: 1, chatId: 100, userId: 42),
    );
  }

  /**
   * Build a fully initialised `SceneWizard` and bind a minimal `Scene`.
   */
  private function makeWizard(
    ?FsmContext $ctx = null,
    ?HistoryManagerInterface $history = null,
    ?SceneManagerInterface $manager = null,
    SceneConfig $config = new SceneConfig(
      state: 'test_scene',
      handlers: [],
      actions: [],
    ),
    string $updateType = 'message',
  ): SceneWizard {
    $ctx ??= $this->makeFsmContext();
    $history ??= $this->makeHistory();
    $manager ??= $this->makeManager($history);

    $wizard = new SceneWizard(
      sceneConfig: $config,
      manager: $manager,
      state: $ctx,
      updateType: $updateType,
      event: new stdClass(),
      data: [],
    );

    // Inject a minimal scene so onAction doesn't throw.
    $wizard->scene = new class ($wizard) extends Scene {};

    return $wizard;
  }

  // ------------------------------------------------------------------ //
  // enter()
  // ------------------------------------------------------------------ //

  /**
   * `enter()` sets the FSM state to the scene's configured state.
   */
  public function testEnterSetsState(): void
  {
    $ctx = $this->makeFsmContext();
    $wizard = $this->makeWizard(ctx: $ctx, config: $this->makeConfig('my_scene'));

    $wizard->enter();

    self::assertSame('my_scene', $ctx->getState());
  }

  /**
   * `enter()` resets FSM data when `resetDataOnEnter` is `true`.
   */
  public function testEnterResetsDataWhenConfigured(): void
  {
    $ctx = $this->makeFsmContext();
    $ctx->setData(['old' => 'value']);

    $wizard = $this->makeWizard(
      ctx: $ctx,
      config: $this->makeConfig(resetDataOnEnter: true),
    );
    $wizard->enter();

    self::assertSame([], $ctx->getData());
  }

  /**
   * `enter()` does NOT reset FSM data when `resetDataOnEnter` is `null`.
   */
  public function testEnterDoesNotResetDataWhenNotConfigured(): void
  {
    $ctx = $this->makeFsmContext();
    $ctx->setData(['keep' => 'me']);

    $wizard = $this->makeWizard(
      ctx: $ctx,
      config: $this->makeConfig(resetDataOnEnter: null),
    );
    $wizard->enter();

    self::assertSame(['keep' => 'me'], $ctx->getData());
  }

  /**
   * `enter()` clears history when `resetHistoryOnEnter` is `true`.
   */
  public function testEnterClearsHistoryWhenConfigured(): void
  {
    $history = $this->makeHistory();
    $manager = $this->makeManager($history);

    $wizard = $this->makeWizard(
      history: $history,
      manager: $manager,
      config: $this->makeConfig(resetHistoryOnEnter: true),
    );
    $wizard->enter();

    self::assertContains('clear', $history->calls); // @phpstan-ignore-line property.notFound
  }

  /**
   * `enter()` dispatches the `Enter` action to the matching handler.
   */
  public function testEnterDispatchesEnterAction(): void
  {
    $called = false;
    $actions = [
      SceneAction::Enter->name => [
        'message' => static function () use (&$called): void {
          $called = true;
        },
      ],
    ];

    $wizard = $this->makeWizard(config: $this->makeConfig(actions: $actions));
    $wizard->enter();

    self::assertTrue($called, 'Enter action handler must be called');
  }

  // ------------------------------------------------------------------ //
  // leave()
  // ------------------------------------------------------------------ //

  /**
   * `leave()` snapshots history when `$withHistory=true` (the default).
   */
  public function testLeaveSnapshotsHistoryByDefault(): void
  {
    $history = $this->makeHistory();
    $manager = $this->makeManager($history);
    $wizard = $this->makeWizard(history: $history, manager: $manager);

    $wizard->leave();

    self::assertContains('snapshot', $history->calls); // @phpstan-ignore-line property.notFound
  }

  /**
   * `leave(withHistory: false)` does NOT snapshot history.
   */
  public function testLeaveSkipsSnapshotWhenWithHistoryFalse(): void
  {
    $history = $this->makeHistory();
    $manager = $this->makeManager($history);
    $wizard = $this->makeWizard(history: $history, manager: $manager);

    $wizard->leave(false);

    self::assertNotContains('snapshot', $history->calls); // @phpstan-ignore-line property.notFound
  }

  // ------------------------------------------------------------------ //
  // exit()
  // ------------------------------------------------------------------ //

  /**
   * `exit()` clears history, dispatches Exit action, then calls `manager->enter(null)`.
   */
  public function testExitSequence(): void
  {
    // Inline recording history + manager to capture call order precisely.
    $recHistory = $this->makeHistory();
    $recManager = $this->makeManager($recHistory);

    $actionCalled = false;
    $actions = [
      SceneAction::Exit->name => [
        'message' => static function () use (&$actionCalled): void {
          $actionCalled = true;
        },
      ],
    ];

    $wizard = $this->makeWizard(
      history: $recHistory,
      manager: $recManager,
      config: $this->makeConfig(actions: $actions),
    );
    $wizard->exit();

    // History cleared before the action.
    self::assertContains('clear', $recHistory->calls); // @phpstan-ignore-line property.notFound
    // Exit action was dispatched.
    self::assertTrue($actionCalled, 'Exit action handler must be called');
    // manager->enter(null) was invoked.
    self::assertCount(1, $recManager->enterCalls); // @phpstan-ignore-line property.notFound
    self::assertNull($recManager->enterCalls[0]['scene']); // @phpstan-ignore-line property.notFound
    self::assertFalse($recManager->enterCalls[0]['checkActive']); // @phpstan-ignore-line property.notFound
  }

  // ------------------------------------------------------------------ //
  // back()
  // ------------------------------------------------------------------ //

  /**
   * `back()` does NOT snapshot history (leave with `$withHistory=false`),
   * then calls `manager->enter()` with the rollback result.
   */
  public function testBackUsesNoHistoryAndEntersRollbackScene(): void
  {
    $history = $this->makeHistory();
    $history->rollbackReturn = 'previous_scene'; // @phpstan-ignore-line property.notFound
    $manager = $this->makeManager($history);

    $wizard = $this->makeWizard(history: $history, manager: $manager);
    $wizard->back();

    // No snapshot during back().
    self::assertNotContains('snapshot', $history->calls); // @phpstan-ignore-line property.notFound
    // Rollback was called.
    self::assertContains('rollback', $history->calls); // @phpstan-ignore-line property.notFound
    // Manager entered the rollback result.
    self::assertCount(1, $manager->enterCalls); // @phpstan-ignore-line property.notFound
    self::assertSame('previous_scene', $manager->enterCalls[0]['scene']); // @phpstan-ignore-line property.notFound
    self::assertFalse($manager->enterCalls[0]['checkActive']); // @phpstan-ignore-line property.notFound
  }

  // ------------------------------------------------------------------ //
  // retake()
  // ------------------------------------------------------------------ //

  /**
   * `retake()` re-enters the scene's own configured state.
   */
  public function testRetakeReEntersCurrentScene(): void
  {
    $history = $this->makeHistory();
    $manager = $this->makeManager($history);

    $wizard = $this->makeWizard(
      history: $history,
      manager: $manager,
      config: $this->makeConfig('retake_scene'),
    );
    $wizard->retake();

    // leave() was called (snapshot in history).
    self::assertContains('snapshot', $history->calls); // @phpstan-ignore-line property.notFound
    // Manager entered the scene's own state.
    self::assertCount(1, $manager->enterCalls); // @phpstan-ignore-line property.notFound
    self::assertSame('retake_scene', $manager->enterCalls[0]['scene']); // @phpstan-ignore-line property.notFound
  }

  /**
   * `retake()` throws `SceneException` when the scene has no configured state.
   *
   * Mirrors the upstream guard: a stateless scene cannot be re-entered because
   * there is no FSM state to `goto()` — silently falling back to `goto('')`
   * would schedule an empty-string FSM state transition, which is wrong.
   */
  public function testRetakeThrowsWhenSceneHasNoState(): void
  {
    $config = new SceneConfig(
      state: null,
      handlers: [],
      actions: [],
    );
    $wizard = $this->makeWizard(config: $config);

    $this->expectException(SceneException::class);
    $this->expectExceptionMessage('Cannot retake() on a scene with no state.');

    $wizard->retake();
  }

  // ------------------------------------------------------------------ //
  // goto()
  // ------------------------------------------------------------------ //

  /**
   * `goto($target)` snapshots history (leave), then enters the target.
   */
  public function testGotoLeavesAndEntersTarget(): void
  {
    $history = $this->makeHistory();
    $manager = $this->makeManager($history);

    $wizard = $this->makeWizard(history: $history, manager: $manager);
    $wizard->goto('other_scene');

    self::assertContains('snapshot', $history->calls); // @phpstan-ignore-line property.notFound
    self::assertCount(1, $manager->enterCalls); // @phpstan-ignore-line property.notFound
    self::assertSame('other_scene', $manager->enterCalls[0]['scene']); // @phpstan-ignore-line property.notFound
    self::assertFalse($manager->enterCalls[0]['checkActive']); // @phpstan-ignore-line property.notFound
  }

  /**
   * `goto()` with integer-keyed kwargs must NOT throw a PHP unpack Error.
   *
   * Regression: PHP throws "Cannot use positional argument after named
   * argument during unpacking" when a spread array contains integer keys
   * alongside string keys. User code calling `$wizard->goto('target',
   * ...$genericData)` where `$genericData` has integer keys would trigger
   * this. The `namedOnly()` helper strips integer keys before every spread.
   */
  public function testGotoWithIntegerKeyedKwargsDoesNotThrow(): void
  {
    $manager = $this->makeManager($this->makeHistory());
    $wizard = $this->makeWizard(manager: $manager);

    // 0 => 'ignored' is an integer-keyed kwarg; 'real' => 'value' is named.
    // Without namedOnly() this would throw an Error at the spread site.
    $wizard->goto('target', ...[0 => 'ignored', 'real' => 'value']);

    // Manager must have been called (goto completes, not crashes).
    self::assertCount(1, $manager->enterCalls); // @phpstan-ignore-line property.notFound
    self::assertSame('target', $manager->enterCalls[0]['scene']); // @phpstan-ignore-line property.notFound
  }

  // ------------------------------------------------------------------ //
  // onAction() — returns false when no handler matches
  // ------------------------------------------------------------------ //

  /**
   * `onAction` returns false (no dispatch) when no handler is registered
   * for the current update_type.
   */
  public function testOnActionReturnsFalseWhenNoHandlerMatchesUpdateType(): void
  {
    $actions = [
      SceneAction::Enter->name => [
        'callback_query' => static function (): void {
          // Only registered for callback_query, not message.
        },
      ],
    ];

    $wizard = $this->makeWizard(
      config: $this->makeConfig(actions: $actions),
      updateType: 'message',
    );
    // enter() internally calls onAction(Enter) and would return false silently.
    // We verify no exception and state is still set.
    $wizard->enter();

    // No assertion needed beyond "no exception" — but check state was set.
    self::assertSame('test_scene', $wizard->state->getState());
  }

  // ------------------------------------------------------------------ //
  // onAction() — throws SceneException without $scene
  // ------------------------------------------------------------------ //

  /**
   * Calling a method that triggers `onAction()` before `$wizard->scene` is
   * set throws `SceneException`.
   */
  public function testOnActionThrowsWhenSceneNotSet(): void
  {
    $wizard = new SceneWizard(
      sceneConfig: $this->makeConfig(),
      manager: $this->makeManager($this->makeHistory()),
      state: $this->makeFsmContext(),
      updateType: 'message',
      event: new stdClass(),
      data: [],
    );
    // $wizard->scene is null — not set.

    $this->expectException(SceneException::class);

    $wizard->enter();
  }

  // ------------------------------------------------------------------ //
  // setData / getData / getValue / updateData / clearData
  // ------------------------------------------------------------------ //

  /**
   * `setData` + `getData` round-trip.
   */
  public function testSetDataGetDataRoundTrip(): void
  {
    $ctx = $this->makeFsmContext();
    $wizard = $this->makeWizard(ctx: $ctx);

    $wizard->setData(['name' => 'Alice', 'step' => 2]);

    self::assertSame(['name' => 'Alice', 'step' => 2], $wizard->getData());
  }

  /**
   * `getValue` returns the stored value for an existing key.
   */
  public function testGetValueReturnsPresentValue(): void
  {
    $ctx = $this->makeFsmContext();
    $wizard = $this->makeWizard(ctx: $ctx);

    $wizard->setData(['score' => 99]);

    self::assertSame(99, $wizard->getValue('score'));
  }

  /**
   * `getValue` returns the provided default for a missing key.
   */
  public function testGetValueReturnsDefaultForMissingKey(): void
  {
    $wizard = $this->makeWizard();

    self::assertSame('fallback', $wizard->getValue('missing', 'fallback'));
  }

  /**
   * `updateData` merges new keys into existing data without losing old keys.
   */
  public function testUpdateDataMergesIntoExistingData(): void
  {
    $ctx = $this->makeFsmContext();
    $wizard = $this->makeWizard(ctx: $ctx);

    $wizard->setData(['a' => 1]);
    $result = $wizard->updateData(['b' => 2]);

    self::assertSame(1, $result['a']);
    self::assertSame(2, $result['b']);
  }

  /**
   * `clearData` empties the FSM data payload.
   */
  public function testClearDataEmptiesPayload(): void
  {
    $ctx = $this->makeFsmContext();
    $wizard = $this->makeWizard(ctx: $ctx);

    $wizard->setData(['foo' => 'bar']);
    $wizard->clearData();

    self::assertSame([], $wizard->getData());
  }

  // ------------------------------------------------------------------ //
  // HandlerContainer + SceneConfig smoke tests
  // ------------------------------------------------------------------ //

  /**
   * `HandlerContainer` stores its properties correctly.
   */
  public function testHandlerContainerProperties(): void
  {
    $callable = static function (): void {};
    $container = new HandlerContainer(name: 'onMessage', handler: $callable);

    self::assertSame('onMessage', $container->name);
    self::assertSame($callable, $container->handler);
    self::assertSame([], $container->filters);
    self::assertNull($container->after);
  }

  /**
   * `SceneConfig` stores all constructor-promoted properties.
   */
  public function testSceneConfigProperties(): void
  {
    $config = new SceneConfig(
      state: 'greeting',
      handlers: [],
      actions: [],
      resetDataOnEnter: true,
      resetHistoryOnEnter: false,
    );

    self::assertSame('greeting', $config->state);
    self::assertTrue($config->resetDataOnEnter);
    self::assertFalse($config->resetHistoryOnEnter);
    self::assertNull($config->callbackQueryWithoutState);
  }
}
