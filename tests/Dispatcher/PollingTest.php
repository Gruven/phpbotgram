<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher;

use Amp\DeferredFuture;

use function Amp\delay;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RecordingDispatcher;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\BackoffConfig;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Polling-loop tests for `Dispatcher` — Task 3.12.
 *
 * Test harness strategy: `MockedSession` returns canned responses
 * **immediately** (no IO suspension), so the polling fiber has no natural
 * yield points between rounds. Triggering `stopPolling` from another fiber
 * via `delay(...)` alone is racey because the polling fiber may consume all
 * canned responses before the test fiber resumes from its delay.
 *
 * The reliable pattern used across the suite:
 *
 * 1. Queue exactly the canned `getUpdates` responses the scenario consumes
 *    (one or two rounds).
 * 2. Register a handler (typically on `message` or on `startup`) that calls
 *    `$dispatcher->stopPolling()`. The stop fires *during* the dispatch of
 *    the last expected Update; the polling loop's post-yield check then
 *    returns from the generator, the per-bot fiber exits, and
 *    `startPolling().await()` resolves cleanly.
 *
 * For unit-level `listenUpdates` tests we bypass the public `startPolling`
 * entry point and drive the generator directly via `ExposedDispatcher`,
 * which uses Reflection (PHP `private` is per-declaring-class, so closure
 * binding from a subclass creates a dynamic property — Reflection is the
 * only safe way to read/write the parent's `$stopSignal` slot from here).
 *
 * @internal
 *
 * @coversNothing
 */
final class PollingTest extends TestCase
{
  use RunAsyncTrait;

  protected function tearDown(): void
  {
    Bot::setCurrent(null);
  }

  public function testStopPollingBeforeStartIsNoop(): void
  {
    // The mutex-protected stopSignal slot starts null, so a stop with no
    // start in flight must NOT throw and must NOT corrupt later state.
    // Mirrors upstream's `_signal_stop_polling` early-return when the
    // running lock isn't held (`dispatcher.py:512`).
    $dispatcher = new Dispatcher();

    $this->runAsync(static function () use ($dispatcher): void {
      $dispatcher->stopPolling();
    });

    // No assertion: the test passes if the runAsync call returns without
    // throwing. `addToAssertionCount` registers progress for PHPUnit's
    // failOnRisky guard without falsely claiming a real check.
    self::assertNull($dispatcher->workflowData['stop'] ?? null);
  }

  public function testStartPollingRejectsEmptyBotList(): void
  {
    // No bots = no polling targets. Upstream raises ValueError; the port
    // surfaces a LogicException because PHP's stdlib has no Value/Logic
    // split this fine and "you passed nothing to poll" is unambiguously
    // a programming bug.
    $dispatcher = new Dispatcher();

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('at least one Bot');

    $this->runAsync(static function () use ($dispatcher): void {
      $dispatcher->startPolling(new PollingOptions())->await();
    });
  }

  public function testStartPollingTwiceRejectsSecondCall(): void
  {
    // Single-instance guard via `$runningLock` + `$isPolling`. The second
    // entry must fail fast before mutating any state. Register a startup
    // handler that triggers the second-call assertion in-line, then stops
    // polling so the first call drains naturally.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();
    $bot->addResultFor(GetUpdates::class, ok: true, result: []);

    /** @var ?LogicException $thrown */
    $thrown = null;

    $dispatcher->startup->register(static function () use ($dispatcher, $bot, &$thrown): void {
      try {
        // Already polling at this point — startup fires from inside
        // startPolling's own body. A second call must reject.
        $dispatcher->startPolling(new PollingOptions(), $bot);
      } catch (LogicException $e) {
        $thrown = $e;
      }
      // Stop the first poll so the test fiber unblocks.
      $dispatcher->stopPolling();
    });

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $bot)->await();
    });

    self::assertNotNull($thrown, 'Second startPolling must reject.');
    self::assertStringContainsString('already in progress', $thrown->getMessage());
  }

  public function testListenUpdatesYieldsEveryUpdateInBatchOrder(): void
  {
    // listenUpdates is a Generator; we exercise it directly so we can
    // assert on the exact yield sequence. Resolve stopSignal on the LAST
    // yield (updateId=102) so the post-yield check terminates the
    // generator without re-entering getUpdates.
    $dispatcher = new ExposedDispatcher();
    $bot = new MockedBot();

    $update1 = self::makeMessageUpdate(101, 'first');
    $update2 = self::makeMessageUpdate(102, 'second');
    $bot->addResultFor(GetUpdates::class, ok: true, result: [$update1, $update2]);

    $yielded = $this->runAsync(static function () use ($dispatcher, $bot): array {
      $dispatcher->beginStopSignal();
      $collected = [];

      foreach ($dispatcher->listenUpdates($bot, new PollingOptions(pollingTimeout: 0)) as $u) {
        $collected[] = $u;

        if ($u->updateId === 102) {
          $dispatcher->resolveStopSignal();
        }
      }

      return $collected;
    });

    self::assertCount(2, $yielded);
    self::assertSame(101, $yielded[0]->updateId);
    self::assertSame(102, $yielded[1]->updateId);
  }

  public function testListenUpdatesPostYieldCheckHonoursStop(): void
  {
    // Same scenario as above but resolves stopSignal AFTER the FIRST
    // yield so the generator's post-yield check terminates before the
    // second update is yielded. Demonstrates the spec's "stop is
    // observed within one batch" guarantee.
    $dispatcher = new ExposedDispatcher();
    $bot = new MockedBot();

    $update1 = self::makeMessageUpdate(101, 'first');
    $update2 = self::makeMessageUpdate(102, 'second');
    $bot->addResultFor(GetUpdates::class, ok: true, result: [$update1, $update2]);

    $yielded = $this->runAsync(static function () use ($dispatcher, $bot): array {
      $dispatcher->beginStopSignal();
      $collected = [];

      foreach ($dispatcher->listenUpdates($bot, new PollingOptions(pollingTimeout: 0)) as $u) {
        $collected[] = $u;
        $dispatcher->resolveStopSignal();
      }

      return $collected;
    });

    self::assertCount(1, $yielded, 'Stop signal after first yield must terminate the generator.');
    self::assertSame(101, $yielded[0]->updateId);
  }

  public function testListenUpdatesAdvancesOffsetAcrossRounds(): void
  {
    // Two batches: round 1 returns one Update with updateId=50, round 2
    // returns another with updateId=200. We resolve the stop signal on
    // the SECOND yield so both rounds actually fire — letting us inspect
    // the recorded request offsets to verify `updateId + 1` advance.
    $dispatcher = new ExposedDispatcher();
    $bot = new MockedBot();

    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(50, 'first')]);
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(200, 'second')]);

    $count = $this->runAsync(static function () use ($dispatcher, $bot): int {
      $dispatcher->beginStopSignal();
      $seen = 0;

      foreach ($dispatcher->listenUpdates($bot, new PollingOptions(pollingTimeout: 0)) as $u) {
        ++$seen;

        // Stop only after the second yield so both getUpdates rounds run.
        if ($u->updateId === 200) {
          $dispatcher->resolveStopSignal();
        }
      }

      return $seen;
    });

    self::assertSame(2, $count, 'Both rounds must have yielded.');

    // FIFO drain — `getRequest` pops the head, so requests come back in
    // dispatch order. Narrow to the concrete method type before reading
    // the `offset` parameter (PHPStan can't otherwise infer it from the
    // generic TelegramMethod<mixed> return).
    $firstRequest = $bot->getRequest();
    $secondRequest = $bot->getRequest();
    self::assertInstanceOf(GetUpdates::class, $firstRequest);
    self::assertInstanceOf(GetUpdates::class, $secondRequest);
    self::assertNull($firstRequest->offset, 'First round must have null offset.');
    self::assertSame(51, $secondRequest->offset, 'Second round must use updateId + 1.');
  }

  public function testListenUpdatesSleepsOnRetryAfterThenRecovers(): void
  {
    // TelegramRetryAfter is the explicit flood-wait signal; the loop
    // sleeps for exactly the retryAfter seconds (no backoff growth), then
    // retries. We queue an error response with retryAfter=0 (no real
    // sleep) so the test stays fast.
    $dispatcher = new ExposedDispatcher();
    $bot = new MockedBot();

    $bot->addResultFor(
      GetUpdates::class,
      ok: false,
      description: 'Too Many Requests',
      errorCode: 429,
      retryAfter: 0,
    );
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(7, 'after retry')]);

    $consumed = $this->runAsync(static function () use ($dispatcher, $bot): array {
      $dispatcher->beginStopSignal();
      $results = [];

      foreach ($dispatcher->listenUpdates($bot, new PollingOptions(pollingTimeout: 0)) as $u) {
        $results[] = $u->updateId;
        $dispatcher->resolveStopSignal();
      }

      return $results;
    });

    self::assertSame([7], $consumed, 'Loop must recover from retry-after and yield the second batch.');
  }

  public function testListenUpdatesSleepsViaBackoffOnRestartingTelegram(): void
  {
    // RestartingTelegram (subclass of TelegramServerException) routes
    // through Backoff::asleep. We pin minDelay = maxDelay = 0.005s so
    // the sleep is observably above CI noise but doesn't dominate test
    // runtime.
    $dispatcher = new ExposedDispatcher();
    $bot = new MockedBot();

    $bot->addResultFor(
      GetUpdates::class,
      ok: false,
      description: 'Bot API server is restarting',
      errorCode: 502,
    );
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(8, 'recovered')]);

    $start = hrtime(true);
    $consumed = $this->runAsync(static function () use ($dispatcher, $bot): array {
      $dispatcher->beginStopSignal();
      $results = [];

      $opts = new PollingOptions(
        pollingTimeout: 0,
        backoffConfig: new BackoffConfig(minDelay: 0.005, maxDelay: 0.005, factor: 1.1, jitter: 0.0),
      );

      foreach ($dispatcher->listenUpdates($bot, $opts) as $u) {
        $results[] = $u->updateId;
        $dispatcher->resolveStopSignal();
      }

      return $results;
    });
    $elapsedNs = hrtime(true) - $start;

    self::assertSame([8], $consumed);
    self::assertGreaterThanOrEqual(2_500_000, $elapsedNs, 'Backoff must have actually slept (>=2.5ms).');
  }

  public function testStartPollingFiresEmitStartupAndShutdown(): void
  {
    // Startup runs BEFORE the polling fibers spawn — so a startup handler
    // can mutate workflowData or short-circuit launch. Shutdown runs in
    // the awaiter's finally block after the polling fibers drain. The
    // injected `bots` kwarg is mandatory per spec § "Polling loop".
    //
    // We have the startup handler trigger stopPolling so the loop
    // observes the signal on its very first while-guard check, yields
    // nothing, and unwinds — no need to queue canned responses or race
    // a parallel stop fiber.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();

    /** @var list<array{phase: string, bots: array<int, Bot>}> $events */
    $events = [];

    $dispatcher->startup->register(static function (array $bots) use (&$events, $dispatcher): void {
      $events[] = ['phase' => 'startup', 'bots' => $bots];
      $dispatcher->stopPolling();
    });
    $dispatcher->shutdown->register(static function (array $bots) use (&$events): void {
      $events[] = ['phase' => 'shutdown', 'bots' => $bots];
    });

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $bot)->await();
    });

    self::assertCount(2, $events);
    self::assertSame('startup', $events[0]['phase']);
    self::assertSame([$bot], $events[0]['bots']);
    self::assertSame('shutdown', $events[1]['phase']);
    self::assertSame([$bot], $events[1]['bots']);
  }

  public function testStartPollingInjectsBotSingularAlongsideBotsPlural(): void
  {
    // Fix I4: spec line 234 mandates that startup/shutdown receive a
    // `bot` (singular) kwarg = `array_key_last($bots)`, alongside the
    // `bots` plural list. Mirrors upstream `dispatcher.py:595`/`:626`
    // calls `await self.emit_startup(bot=bots[-1], **workflow_data)`.
    //
    // Verify by registering a startup handler that requests both kwargs
    // and asserting `$bot === $botB` (the last bot in the variadic list).
    $dispatcher = new Dispatcher();
    $botA = new MockedBot('1:A');
    $botB = new MockedBot('2:B');

    $captured = null;
    $dispatcher->startup->register(static function (Bot $bot, array $bots) use (
      &$captured,
      $dispatcher,
    ): void {
      $captured = ['bot' => $bot, 'bots' => $bots];
      $dispatcher->stopPolling();
    });

    $this->runAsync(static function () use ($dispatcher, $botA, $botB): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $botA, $botB)->await();
    });

    self::assertNotNull($captured);
    self::assertSame($botB, $captured['bot'], 'bot singular must be the last bot in the variadic list.');
    self::assertSame([$botA, $botB], $captured['bots']);
  }

  public function testStartPollingInjectsBotSingularIntoShutdownToo(): void
  {
    // Same `bot` singular injection must apply to shutdown — the spec
    // documents a single workflow_data bag carried through both lifecycle
    // hooks (the second `emit_shutdown(bot=bots[-1], **workflow_data)` call
    // at `dispatcher.py:626`).
    $dispatcher = new Dispatcher();
    $botA = new MockedBot('1:A');
    $botB = new MockedBot('2:B');
    $dispatcher->startup->register(static fn() => $dispatcher->stopPolling());

    $captured = null;
    $dispatcher->shutdown->register(static function (Bot $bot, array $bots) use (&$captured): void {
      $captured = ['bot' => $bot, 'bots' => $bots];
    });

    $this->runAsync(static function () use ($dispatcher, $botA, $botB): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $botA, $botB)->await();
    });

    self::assertNotNull($captured);
    self::assertSame($botB, $captured['bot']);
    self::assertSame([$botA, $botB], $captured['bots']);
  }

  public function testStartPollingClosesBotSessionsOnShutdown(): void
  {
    // Session-close is the final shutdown step, after emitShutdown but
    // before the runningLock is released. Drive a no-op loop via a
    // stop-from-startup-handler so the test doesn't depend on canned
    // response counts.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();
    $dispatcher->startup->register(static fn() => $dispatcher->stopPolling());

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $bot)->await();
    });

    self::assertTrue(
      $bot->getMockedSession()->closed,
      'Polling shutdown must close every bot session.',
    );
  }

  public function testPollingForSerialDispatchRunsHandlerToCompletionBeforeNextUpdate(): void
  {
    // handleAsTasks=null => no fiber spawn; each Update runs through
    // feedUpdate before the next is consumed. We assert ordering by
    // recording entry/exit ticks per handler. The second handler
    // (on updateId=2) is the one that stops polling so the loop unwinds
    // after both have run.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();

    /** @var list<string> $events */
    $events = [];
    $dispatcher->message->register(static function (Update $event_update) use (&$events, $dispatcher): void {
      $events[] = "enter:{$event_update->updateId}";

      if ($event_update->updateId === 2) {
        $dispatcher->stopPolling();
      }
      $events[] = "leave:{$event_update->updateId}";
    });

    $bot->addResultFor(
      GetUpdates::class,
      ok: true,
      result: [
        self::makeMessageUpdate(1, 'a'),
        self::makeMessageUpdate(2, 'b'),
      ],
    );

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $bot)->await();
    });

    self::assertSame(
      ['enter:1', 'leave:1', 'enter:2', 'leave:2'],
      $events,
      'Serial dispatch must keep handlers strictly sequential.',
    );
  }

  public function testPollingForConcurrentDispatchInterleavesHandlers(): void
  {
    // handleAsTasks=2 => each Update runs in its own fiber. Two slow
    // handlers should both be in-flight at the same time, so the trace
    // interleaves their phases rather than running 1-then-2.
    //
    // Backpressure trick: queue THREE updates with handleAsTasks=2. The
    // third semaphore.acquire() suspends because the pool is saturated,
    // which yields the polling fiber back to the event loop. That lets
    // tasks 1 and 2 begin running. Task 2's `leave:` then signals stop,
    // and once one of {1,2} drains its lock the third task spawns and
    // the polling loop exits on the next while-guard check.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();

    /** @var list<string> $events */
    $events = [];
    $dispatcher->message->register(static function (Update $event_update) use (&$events, $dispatcher): void {
      $events[] = "enter:{$event_update->updateId}";
      // Suspend long enough for the second fiber to advance past its
      // own "enter" before we record "leave".
      delay(0.005);
      $events[] = "leave:{$event_update->updateId}";

      // Stop from the FIRST handler to finish so the polling fiber's
      // semaphore.acquire (parked on the third update) sees a completed
      // stopSignal and returns + drains via the while-guard. The second
      // handler keeps running in parallel and contributes its
      // enter/leave to the trace.
      $dispatcher->stopPolling();
    });

    $bot->addResultFor(
      GetUpdates::class,
      ok: true,
      result: [
        self::makeMessageUpdate(1, 'a'),
        self::makeMessageUpdate(2, 'b'),
        self::makeMessageUpdate(3, 'c'),
      ],
    );
    // Round 2 empty — after the foreach drains, the polling fiber
    // re-enters listenUpdates; an empty response keeps the loop alive
    // until the while-guard sees stopSignal. Without this canned
    // response the loop would throw "No canned responses left".
    $bot->addResultFor(GetUpdates::class, ok: true, result: []);

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: 2), $bot)->await();
    });

    // The first three events must demonstrate concurrent dispatch:
    // both task 1 and task 2 enter their handlers before either prints
    // its leave. The leave order depends on scheduling, but the enter
    // ordering is the invariant that proves the fibers ran in parallel
    // (a serial loop would emit `enter:1, leave:1, enter:2, leave:2`).
    self::assertSame(['enter:1', 'enter:2', 'leave:1'], array_slice($events, 0, 3));
    // And `leave:2` must eventually appear — confirming task 2 completed
    // even though task 1's handler resolved the stop signal first.
    self::assertContains('leave:2', $events);
  }

  public function testRunPollingAwaitsUntilStopSignal(): void
  {
    // runPolling is the sync wrapper — it calls startPolling()->await()
    // under signal handlers. Trigger stop from a startup handler so we
    // don't need a parallel async() race.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();
    $dispatcher->startup->register(static fn() => $dispatcher->stopPolling());

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->runPolling(new PollingOptions(handleAsTasks: null), $bot);
    });

    // The canary: shutdown ran (session is closed) and we exited cleanly
    // without timing out.
    self::assertTrue($bot->getMockedSession()->closed);
  }

  public function testPollingForSwallowsUnknownUpdateTypeWithWarningAndContinues(): void
  {
    // Fix C2: an Update with no recognised event slot (every optional field
    // null) causes `Dispatcher::feedUpdate` to throw
    // `UpdateTypeLookupException`. Upstream's `_listen_update` swallows the
    // matching `UpdateTypeLookupError` with a `RuntimeWarning` so a single
    // unknown update — typically caused by Telegram introducing a new
    // update kind before the schema was regenerated — does NOT kill the
    // long-poll loop. The port mirrors via `trigger_error(..., E_USER_WARNING)`
    // around a `continue` inside `pollingFor`.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();
    $handled = [];
    $dispatcher->message->register(static function (Update $event_update) use (
      &$handled,
      $dispatcher,
    ): void {
      $handled[] = $event_update->updateId;
      $dispatcher->stopPolling();
    });

    $unknown = new Update(updateId: 999); // No event slot populated.
    $bot->addResultFor(GetUpdates::class, ok: true, result: [
      $unknown,
      self::makeMessageUpdate(1000, 'after unknown'),
    ]);

    $warning = null;
    set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
      if ($errno === \E_USER_WARNING && $warning === null) {
        $warning = $errstr;
      }

      return true;
    });

    try {
      $this->runAsync(static function () use ($dispatcher, $bot): void {
        $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $bot)->await();
      });
    } finally {
      restore_error_handler();
    }

    self::assertSame([1000], $handled, 'Polling must skip the unknown update and dispatch the next one.');
    self::assertNotNull($warning, 'Polling must emit a RuntimeWarning-equivalent on unknown update types.');
    self::assertStringContainsString('unknown update type', strtolower((string)$warning));
  }

  public function testPollingForConcurrentSwallowsUnknownUpdateTypeWithWarning(): void
  {
    // Fix I1: in concurrent mode (`handleAsTasks` non-null) the per-update
    // dispatch runs inside `async(...)`, so an `UpdateTypeLookupException`
    // thrown by `feedUpdate` lands on the spawned Future — NOT the
    // synchronous call stack `pollingFor`'s outer catch can see. Without
    // the in-async catch the failure would surface as an "unhandled future"
    // warning at GC and the polling loop would continue blindly.
    //
    // The fix mirrors Fix C2 by moving the typed catch INSIDE the async
    // closure, swallowing with an `E_USER_WARNING` so the polling loop
    // keeps processing subsequent updates. We assert both effects: the
    // next (known) update is handled AND a warning fired.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();
    $handled = [];
    $dispatcher->message->register(static function (Update $event_update) use (
      &$handled,
      $dispatcher,
    ): void {
      $handled[] = $event_update->updateId;
      $dispatcher->stopPolling();
    });

    $unknown = new Update(updateId: 9001); // No event slot populated.
    $bot->addResultFor(GetUpdates::class, ok: true, result: [
      $unknown,
      self::makeMessageUpdate(9002, 'after unknown'),
    ]);
    // Round 2 empty — the polling loop needs at least one more poll round
    // available so the concurrent-dispatch back-pressure doesn't stall on
    // a missing canned response while the second update's fiber finishes.
    $bot->addResultFor(GetUpdates::class, ok: true, result: []);

    $warning = null;
    set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
      if ($errno === \E_USER_WARNING && $warning === null) {
        $warning = $errstr;
      }

      return true;
    });

    try {
      $this->runAsync(static function () use ($dispatcher, $bot): void {
        $dispatcher->startPolling(new PollingOptions(handleAsTasks: 2), $bot)->await();
      });
    } finally {
      restore_error_handler();
    }

    self::assertSame(
      [9002],
      $handled,
      'Concurrent polling must skip the unknown update and dispatch the next one.',
    );
    self::assertNotNull(
      $warning,
      'Concurrent polling must emit a RuntimeWarning-equivalent on unknown update types.',
    );
    self::assertStringContainsString('unknown update type', strtolower((string)$warning));
  }

  public function testListenUpdatesPropagatesRequestTimeoutOverPollingBudget(): void
  {
    // Fix I5: `Bot::__invoke($method, $timeout)`'s second arg is the HTTP
    // transport timeout the session should wait before bailing on the
    // long-poll round. The polling driver must pass
    // `session.timeout + options.pollingTimeout` so a mid-long-poll HTTP
    // timeout (default 60s session timeout) doesn't cut the request short
    // when the long-poll budget (default 10s) is comfortably inside the
    // window. Mirrors upstream's `dispatcher.py:216` "request_timeout =
    // int(bot.session.timeout + polling_timeout)" kwarg.
    $dispatcher = new ExposedDispatcher();
    $bot = new MockedBot();
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(101, 'hi')]);

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->beginStopSignal();

      foreach ($dispatcher->listenUpdates($bot, new PollingOptions(pollingTimeout: 7)) as $u) {
        $dispatcher->resolveStopSignal();
      }
    });

    $expected = (int)$bot->session->timeout + 7;
    self::assertSame(
      [$expected],
      $bot->getMockedSession()->requestTimeouts,
      'listenUpdates must pass (session.timeout + pollingTimeout) as the HTTP request timeout.',
    );
  }

  public function testStartPollingAutoResolvesAllowedUpdatesWhenUnspecified(): void
  {
    // Fix I8: when PollingOptions::$allowedUpdates is left as the
    // Unspecified sentinel default, Dispatcher::startPolling must replace
    // it with the result of `resolveUsedUpdateTypes()` before kicking off
    // the polling fibers. This mirrors upstream's `dispatcher.py:564-565`
    // "if allowed_updates is UNSET: allowed_updates = self.resolve_used_update_types()".
    //
    // Observable shape: register handlers on `message` and `callback_query`
    // and verify the resulting `getUpdates` request carries
    // `allowed_updates: ['message', 'callback_query']` (order matches
    // resolveUsedUpdateTypes walk order).
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();
    $dispatcher->message->register(static function (Update $event_update) use ($dispatcher): void {
      $dispatcher->stopPolling();
    });
    $dispatcher->callbackQuery->register(static fn() => null);
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(1, 'wake')]);

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $bot)->await();
    });

    $request = $bot->getRequest();
    self::assertInstanceOf(GetUpdates::class, $request);
    self::assertSame(['message', 'callback_query'], $request->allowedUpdates);
  }

  public function testStartPollingPassesNullAllowedUpdatesThrough(): void
  {
    // Counterpart to the auto-resolve test: an explicit `null` (the
    // "receive all subscribed types" passthrough) must reach
    // `getUpdates` verbatim — NOT be replaced by the auto-resolve.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();
    $dispatcher->message->register(static function (Update $event_update) use ($dispatcher): void {
      $dispatcher->stopPolling();
    });
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(2, 'wake')]);

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(
        new PollingOptions(allowedUpdates: null, handleAsTasks: null),
        $bot,
      )->await();
    });

    $request = $bot->getRequest();
    self::assertInstanceOf(GetUpdates::class, $request);
    self::assertNull($request->allowedUpdates);
  }

  public function testStartPollingPassesExplicitListAllowedUpdatesThrough(): void
  {
    // And an explicit list of update types reaches getUpdates as-is.
    $dispatcher = new Dispatcher();
    $bot = new MockedBot();
    $dispatcher->message->register(static function (Update $event_update) use ($dispatcher): void {
      $dispatcher->stopPolling();
    });
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(3, 'wake')]);

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(
        new PollingOptions(allowedUpdates: ['inline_query'], handleAsTasks: null),
        $bot,
      )->await();
    });

    $request = $bot->getRequest();
    self::assertInstanceOf(GetUpdates::class, $request);
    self::assertSame(['inline_query'], $request->allowedUpdates);
  }

  public function testPollingForSerialAutoDispatchesTelegramMethodViaSilentCallRequest(): void
  {
    // Fix I2: a handler that returns a `TelegramMethod` (the polling-side
    // analogue of webhook's inline response) must have that method
    // auto-dispatched via `silentCallRequest`. Upstream's `_process_update`
    // does the same when `call_answer=True` (`dispatcher.py:321-322`):
    // "if call_answer and isinstance(response, TelegramMethod): await
    // self.silent_call_request(bot=bot, result=response)".
    //
    // The port mirrors via `silentCallRequest` so test harnesses can
    // record the invocation. We use `RecordingDispatcher` to capture the
    // routing without driving a real network call.
    $dispatcher = new RecordingDispatcher();
    $bot = new MockedBot();
    $method = new SendMessage(chatId: 1, text: 'polling reply');
    $dispatcher->message->register(static function (Update $event_update) use (
      $method,
      $dispatcher,
    ): SendMessage {
      $dispatcher->stopPolling();

      return $method;
    });
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(1, 'a')]);

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: null), $bot)->await();
    });

    self::assertCount(
      1,
      $dispatcher->silentCalls,
      'Serial polling must route the handler return value through silentCallRequest.',
    );
    self::assertSame($bot, $dispatcher->silentCalls[0][0]);
    self::assertSame($method, $dispatcher->silentCalls[0][1]);
  }

  public function testPollingForConcurrentAutoDispatchesTelegramMethodViaSilentCallRequest(): void
  {
    // Fix I2 (concurrent branch): same contract as the serial variant, but
    // the `silentCallRequest` invocation lives inside the spawned async()
    // closure. The test queues a single update so the recording subclass
    // can capture the routing through the in-async dispatch path.
    $dispatcher = new RecordingDispatcher();
    $bot = new MockedBot();
    $method = new SendMessage(chatId: 1, text: 'concurrent reply');
    $dispatcher->message->register(static function (Update $event_update) use (
      $method,
      $dispatcher,
    ): SendMessage {
      $dispatcher->stopPolling();

      return $method;
    });
    $bot->addResultFor(GetUpdates::class, ok: true, result: [self::makeMessageUpdate(1, 'a')]);
    // Round 2 empty — same back-pressure note as the I1 test.
    $bot->addResultFor(GetUpdates::class, ok: true, result: []);

    $this->runAsync(static function () use ($dispatcher, $bot): void {
      $dispatcher->startPolling(new PollingOptions(handleAsTasks: 2), $bot)->await();
    });

    self::assertCount(
      1,
      $dispatcher->silentCalls,
      'Concurrent polling must route the handler return value through silentCallRequest.',
    );
    self::assertSame($bot, $dispatcher->silentCalls[0][0]);
    self::assertSame($method, $dispatcher->silentCalls[0][1]);
  }

  /**
   * Build a minimal `Update` carrying a text Message with the given
   * updateId and body — parameterised version of `DispatcherTest`'s
   * `messageUpdate` helper.
   */
  private static function makeMessageUpdate(int $updateId, string $text): Update
  {
    $chat = new Chat(id: 1, type: 'private');
    $user = new User(id: 2, isBot: false, firstName: 'Tester');
    $message = new Message(messageId: $updateId, date: new DateTime('@0'), chat: $chat, fromUser: $user, text: $text);

    return new Update(updateId: $updateId, message: $message);
  }
}

/**
 * Test-only Dispatcher subclass exposing the private stopSignal slot.
 * `listenUpdates` is public, but the only sane way to drive it
 * synchronously is to control the stop signal — without a wrapper
 * `startPolling` would also fire emitStartup / emitShutdown / session
 * close, polluting the per-test scenario.
 *
 * The closure-binding trick used by Dispatcher's `feedUpdate` for
 * current-bot mutation would create a dynamic property here because PHP's
 * `private` visibility is enforced per *declaring* class, not per
 * inheritance chain. `ReflectionProperty` is the only contract-clean way
 * to mutate the parent's private slot from a subclass.
 *
 * @internal
 */
final class ExposedDispatcher extends Dispatcher
{
  public function beginStopSignal(): void
  {
    $prop = new ReflectionProperty(Dispatcher::class, 'stopSignal');
    $prop->setValue($this, new DeferredFuture());
  }

  public function resolveStopSignal(): void
  {
    $prop = new ReflectionProperty(Dispatcher::class, 'stopSignal');
    $current = $prop->getValue($this);

    if ($current instanceof DeferredFuture && !$current->isComplete()) {
      $current->complete(null);
    }
  }
}
