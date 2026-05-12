<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher;

use function Amp\async;

use Amp\DeferredFuture;

use function Amp\delay;

use Amp\Future;

use function Amp\Future\await;

use Amp\Sync\LocalMutex;
use Amp\Sync\LocalSemaphore;
use Generator;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Dispatcher\Middlewares\ErrorsMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\UserContextMiddleware;
use Gruven\PhpBotGram\Exceptions\RestartingTelegram;
use Gruven\PhpBotGram\Exceptions\TelegramNetworkException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Exceptions\UpdateTypeLookupException;
use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Utils\Backoff;
use LogicException;
use Revolt\EventLoop;
use Revolt\EventLoop\UnsupportedFeatureException;
use Throwable;

/**
 * Root router with polling/webhook entry points — port of
 * `aiogram.dispatcher.dispatcher.Dispatcher`.
 *
 * Extends `Router` with three responsibilities:
 *
 * 1. **Default middleware wiring**. The constructor attaches
 *    `UserContextMiddleware` (first) and `ErrorsMiddleware` (second) as
 *    outer middlewares on every non-error observer. Order matters: the
 *    user-context middleware injects the `event_context` / `event_from_user`
 *    / `event_chat` / `event_thread_id` kwargs *before* `ErrorsMiddleware`
 *    runs, so an error handler that catches a handler exception sees the
 *    same context shape any other observer would.
 *
 *    The error observer itself skips outer middleware — it IS the error
 *    handler. Wiring `ErrorsMiddleware` on it would loop forever the first
 *    time an error handler throws.
 *
 * 2. **Update ingress entry points**. `feedUpdate` is the canonical
 *    synchronous dispatch; `feedRawUpdate` deserialises a wire-shaped
 *    payload via `Serializer::load` first; `feedWebhookUpdate` is the
 *    HTTP-webhook variant. Task 3.13 lands the 55-second webhook deadline +
 *    slow-warning on top; in Task 3.10 it is observably identical to
 *    `feedUpdate`.
 *
 * 3. **Webhook fall-through stub `silentCallRequest`**. When a handler
 *    returns a `TelegramMethod` and the webhook is past its deadline, the
 *    dispatcher dispatches the method via `$bot($method)` instead of
 *    inlining it into the HTTP response. Task 3.13 adds the
 *    queue-and-skip-when-deadline-fired semantics; the Task 3.10 baseline
 *    just forwards to `$bot($method)`.
 *
 * Spec deviations from upstream:
 *
 * - **No synthetic 'update' observer.** Upstream attaches every middleware
 *   to a single `self.update` observer and routes inside `_listen_update`.
 *   The port wires middlewares on every observer directly because the
 *   Router is already schema-derived per-type. The behaviour is identical
 *   for the public ingress (`feedUpdate` resolves the wire update_type then
 *   dispatches to that observer); only the wiring topology differs.
 * - **No FSM / storage / events_isolation / disable_fsm parameters.** Those
 *   are Phase 5 territory and will be added when `FSMContextMiddleware`
 *   lands. The constructor takes only `$name` for now.
 * - **`Bot::setCurrent` instead of `with bot.context():`.** PHP's FiberLocal
 *   (via `Revolt\EventLoop\FiberLocal`) is the closest analogue to Python's
 *   `contextvars`. The try/finally guard ensures the binding is unset even
 *   when the dispatch raises.
 */
class Dispatcher extends Router
{
  /**
   * Maps wire-name `update_type` keys to the camelCase PHP property name on
   * `Update`. Derived from `Types/Update.php` (Phase 2 codegen output);
   * kept in sync with `Router::UPDATE_TYPES`.
   *
   * Why the duplicate map: `Router::UPDATE_TYPES` lists the wire names for
   * iteration / `allowed_updates` resolution. The dispatcher additionally
   * needs to **read** the resolved event off the `Update` instance by
   * property name, which is camelCase in PHP (snake_case on the wire). A
   * single lookup table here is cheaper than running `NameMapper::camelize`
   * per dispatch — and any drift from the Update schema is caught by
   * `DispatcherTest::testInheritsObserverMapShapeFromRouter` together with
   * `RouterTest::testUpdateTypesConstantMatchesUpdateSchema`.
   */
  private const array SCHEMA_FIELD_FOR_TYPE = [
    'message' => 'message',
    'edited_message' => 'editedMessage',
    'channel_post' => 'channelPost',
    'edited_channel_post' => 'editedChannelPost',
    'business_connection' => 'businessConnection',
    'business_message' => 'businessMessage',
    'edited_business_message' => 'editedBusinessMessage',
    'deleted_business_messages' => 'deletedBusinessMessages',
    'guest_message' => 'guestMessage',
    'message_reaction' => 'messageReaction',
    'message_reaction_count' => 'messageReactionCount',
    'inline_query' => 'inlineQuery',
    'chosen_inline_result' => 'chosenInlineResult',
    'callback_query' => 'callbackQuery',
    'shipping_query' => 'shippingQuery',
    'pre_checkout_query' => 'preCheckoutQuery',
    'purchased_paid_media' => 'purchasedPaidMedia',
    'poll' => 'poll',
    'poll_answer' => 'pollAnswer',
    'my_chat_member' => 'myChatMember',
    'chat_member' => 'chatMember',
    'chat_join_request' => 'chatJoinRequest',
    'chat_boost' => 'chatBoost',
    'removed_chat_boost' => 'removedChatBoost',
    'managed_bot' => 'managedBot',
  ];

  /**
   * Workflow-scoped context shared across handlers. Mirrors upstream's
   * `self.workflow_data: dict[str, Any]` (`dispatcher.py:99`). Every
   * `feedUpdate` call merges this into the handler kwargs alongside the
   * per-call `$kwargs`; per-call kwargs win on key collision.
   *
   * Mutable so callers can write `dispatcher.workflowData['db'] = $pdo`
   * during setup. Spec § "Injected dispatcher kwargs" pins the contract.
   *
   * @var array<string, mixed>
   */
  public array $workflowData = [];

  /**
   * Single-instance guard for the polling driver. Acquired in `startPolling`
   * before the `$isPolling` flag is set so a second concurrent invocation on
   * the same Dispatcher can detect and reject. Mirrors upstream
   * `self._running_lock: asyncio.Lock` (`dispatcher.py:100`).
   *
   * Note: `LocalMutex` is single-fiber by nature — the mutex is only
   * meaningful inside an event loop. The mutex protects the
   * `$isPolling` / `$stopSignal` mutation site against another fiber
   * (or a signal handler that resumes a suspended fiber) racing to
   * read-modify-write the same fields.
   */
  private readonly LocalMutex $runningLock;

  /**
   * Boolean toggled inside the `$runningLock` critical section. `true`
   * between `startPolling` entry (after the guard accepts) and the
   * finally-block exit. `stopPolling` reads it via the mutex to decide
   * whether the call is meaningful — calls when no polling is in flight
   * are silently no-op (matches upstream `_signal_stop_polling`'s
   * "if not locked return" guard at `dispatcher.py:512-513`).
   */
  private bool $isPolling = false;

  /**
   * Shared cancellation signal across every per-bot polling fiber. `null`
   * when no polling is in flight; replaced with a fresh DeferredFuture on
   * each `startPolling` call (so a Dispatcher can be re-started after a
   * graceful shutdown). Resolved by `stopPolling` and by the SIGTERM /
   * SIGINT signal handlers.
   *
   * Type-narrowed to `DeferredFuture<null>` because the carried value is
   * unused — only the "completed" status matters.
   *
   * @var ?DeferredFuture<null>
   */
  private ?DeferredFuture $stopSignal = null;

  /**
   * In-flight handler-dispatch fibers, keyed by `spl_object_id($future)`.
   * Populated by `pollingFor` when `handleAsTasks` requests concurrent
   * dispatch; each entry self-cleans via `Future::finally`. Mirrors
   * upstream `self._handle_update_tasks: set[asyncio.Task]`
   * (`dispatcher.py:103`).
   *
   * @var array<int, Future<void>>
   */
  private array $handleUpdateTasks = [];

  public function __construct(?string $name = null)
  {
    parent::__construct($name);
    $this->runningLock = new LocalMutex();

    // Wire UserContextMiddleware first so subsequent middlewares (and the
    // error observer) see the canonical `event_context` keys populated.
    // The error observer itself skips outer middleware because it IS the
    // error handler — wiring ErrorsMiddleware on it would loop indefinitely
    // the first time an error handler throws.
    foreach ($this->observers as $eventName => $observer) {
      if ($eventName === 'error') {
        continue;
      }
      $observer->outerMiddleware(new UserContextMiddleware());
    }

    // ErrorsMiddleware needs a closure that re-enters propagateEvent with
    // the synthetic ErrorEvent so a registered error observer can claim
    // the failure. The closure captures $this; PHP binds it automatically
    // so the call site stays terse.
    $errorsTrigger = fn(string $type, object $event, array $data): mixed => $this->propagateEvent($type, $event, $data);

    foreach ($this->observers as $eventName => $observer) {
      if ($eventName === 'error') {
        continue;
      }
      $observer->outerMiddleware(new ErrorsMiddleware($errorsTrigger));
    }
  }

  /**
   * Top-level synchronous dispatch entry. Resolves the wire update_type
   * from the `Update`, reads the child event slot, binds the bot via
   * `Bot::setCurrent` (FiberLocal), and dispatches through `propagateEvent`
   * with the merged kwargs bag.
   *
   * Kwargs precedence (last-wins on key collision):
   * 1. `$this->workflowData` — dispatcher-scoped defaults
   * 2. `$kwargs` — caller-supplied per-call overrides
   * 3. injected `event_update` (always the resolved Update) and `bot`
   *    (always the bot argument). These two are dispatcher invariants and
   *    cannot be overridden by callers.
   *
   * The `Bot::setCurrent` binding is wrapped in try/finally so the slot is
   * cleared even if the dispatch raises — without that guard a handler
   * exception would leave the binding pointing at the now-irrelevant bot
   * for the next dispatch on the same fiber.
   *
   * @param array<string, mixed> $kwargs Per-call context (state, fsm_storage, …).
   *
   * @throws UpdateTypeLookupException when the Update has no recognised event slot.
   */
  public function feedUpdate(Bot $bot, Update $update, array $kwargs = []): mixed
  {
    $updateType = $update->eventType();

    if ($updateType === null) {
      throw new UpdateTypeLookupException(
        sprintf('Update %d has no recognised event field', $update->updateId),
      );
    }

    // Defensive: the schema-derived map should always contain $updateType,
    // but a stale port could drift from Update.php. Throw the same typed
    // exception so callers don't need to discriminate between the two
    // failure modes.
    $childField = self::SCHEMA_FIELD_FOR_TYPE[$updateType] ?? null;

    if ($childField === null) {
      throw new UpdateTypeLookupException("Unknown update_type: {$updateType}");
    }

    $event = $update->{$childField};

    if (!$event instanceof TelegramObject) {
      // eventType() returned a key but the slot is null — `Update::eventType`
      // is supposed to guard against this. A non-TelegramObject here would
      // also be a bug because every Update slot is typed as a TelegramObject
      // subclass.
      throw new UpdateTypeLookupException(
        sprintf("Update %d's %s field is empty", $update->updateId, $childField),
      );
    }

    Bot::setCurrent($bot);

    try {
      $merged = [
        ...$this->workflowData,
        ...$kwargs,
        'event_update' => $update,
        'bot' => $bot,
      ];

      return $this->propagateEvent($updateType, $event, $merged);
    } finally {
      Bot::setCurrent(null);
    }
  }

  /**
   * Convenience: deserialise a raw payload (typically the JSON-decoded
   * webhook body or a `getUpdates` array element) to an `Update`, then
   * delegate to `feedUpdate`.
   *
   * Mirrors upstream `feed_raw_update` (`dispatcher.py:186-195`). The
   * `Serializer::load` call binds the bot context to the Update tree (every
   * nested TelegramObject sees `$bot` via its `?Bot $bot` constructor
   * parameter), parity with upstream's `Update.model_validate(..., context={"bot": bot})`.
   *
   * @param array<string, mixed> $rawUpdate Wire-shaped (snake_case) payload.
   * @param array<string, mixed> $kwargs Forwarded to feedUpdate.
   */
  public function feedRawUpdate(Bot $bot, array $rawUpdate, array $kwargs = []): mixed
  {
    /** @var Update $update */
    $update = Serializer::load(Update::class, $rawUpdate, $bot);

    return $this->feedUpdate($bot, $update, $kwargs);
  }

  /**
   * Webhook variant — receives the Update directly from the HTTP request
   * handler. Identical to `feedUpdate` for now; Task 3.13 wraps this with
   * the 55-second deadline + slow-warning + `silentCallRequest` fall-through
   * for handlers that return a `TelegramMethod`.
   *
   * @param array<string, mixed> $kwargs
   */
  public function feedWebhookUpdate(Bot $bot, Update $update, array $kwargs = []): mixed
  {
    return $this->feedUpdate($bot, $update, $kwargs);
  }

  /**
   * Webhook fall-through: dispatch a method via `$bot($method)` when the
   * webhook can no longer inline the response in the HTTP body. Task 3.13
   * adds the deadline-aware queue-and-skip behaviour; the Task 3.10
   * baseline is just `$bot($method)`.
   *
   * `silentCallRequest` is a public **instance** method (deviation from
   * upstream's `@classmethod`) so tests can mock it via a recording
   * subclass — upstream's `unittest.mock.patch` of a class method does not
   * translate cleanly to PHP. See spec § "Webhook response contract" for
   * the rationale.
   *
   * @param TelegramMethod<mixed> $method
   */
  public function silentCallRequest(Bot $bot, TelegramMethod $method): mixed
  {
    return $bot($method);
  }

  /**
   * Endless updates reader — port of `aiogram._listen_updates`
   * (`dispatcher.py:198-253`).
   *
   * Implementation is a `Generator` (PHP generators are Fiber-safe under
   * Revolt). Each iteration calls `getUpdates(timeout: pollingTimeout)`
   * inside a try/catch. On success the backoff is reset, every returned
   * Update is yielded to the caller, and `offset` advances by
   * `updateId + 1` (Telegram's confirm-by-incrementing-offset protocol).
   *
   * Retry semantics on failure mirror upstream:
   * - `TelegramRetryAfter`: sleep for the exact `retryAfter` seconds the
   *   API advertised, then retry without consulting the backoff. This is
   *   the explicit flood-wait contract — backoff growth would be wrong.
   * - `RestartingTelegram` / `TelegramNetworkException`: route through
   *   `Backoff::asleep()` so concurrent bots don't retry in lockstep.
   * - Any other Throwable is **re-raised** — it's a bug at the dispatch
   *   layer, not a transient API hiccup. Upstream catches everything; the
   *   port narrows the catch because a typed dispatch path is in scope
   *   here (TelegramApiException hierarchy), and ErrorsMiddleware will
   *   already have unwound user-level errors before they reach this loop.
   *
   * Loop termination: between rounds we inspect `$stopSignal->isComplete()`.
   * Inside a Telegram long-poll the loop is parked in the HTTP transport
   * (or in `delay()` during a retry sleep). The signal fires the fiber that
   * called `stopPolling`; the polling fiber notices on its next round.
   *
   * @return Generator<int, Update, mixed, void>
   */
  public function listenUpdates(Bot $bot, PollingOptions $options): Generator
  {
    $offset = null;
    $backoff = new Backoff($options->backoffConfig);

    while ($this->stopSignal === null || !$this->stopSignal->isComplete()) {
      try {
        /** @var list<Update> $updates */
        $updates = $bot(new GetUpdates(
          offset: $offset,
          timeout: $options->pollingTimeout,
          allowedUpdates: $options->allowedUpdates,
        ));
      } catch (TelegramRetryAfter $e) {
        delay((float)$e->retryAfter);

        continue;
      } catch (RestartingTelegram|TelegramNetworkException) {
        $backoff->asleep();

        continue;
      }

      // Reset backoff on success — upstream resets on the failed→succeeded
      // transition specifically; calling `reset()` unconditionally is
      // observably equivalent because the counter is zero anyway on the
      // happy path.
      $backoff->reset();

      foreach ($updates as $update) {
        yield $update;
        $offset = $update->updateId + 1;

        // Re-check stop after each yield so callers using a per-update
        // stop pattern (e.g. tests calling stopPolling from inside a
        // handler) terminate without dispatching the rest of the batch.
        if ($this->stopSignal !== null && $this->stopSignal->isComplete()) {
          return;
        }
      }
    }
  }

  /**
   * Internal polling driver for a single bot — port of `aiogram._polling`
   * (`dispatcher.py:354-418`). Consumes `listenUpdates` and dispatches each
   * Update via `feedUpdate`, honoring `handleAsTasks` for concurrent
   * fan-out.
   *
   * Three modes (collapsed from upstream's `handle_as_tasks` bool +
   * `tasks_concurrency_limit` int|None pair):
   * - `handleAsTasks === null` => serial. Each `feedUpdate` runs inline;
   *   the next Update is consumed only after the previous handler returns.
   * - `handleAsTasks === int n` => concurrent with `LocalSemaphore`
   *   of size n. Each Update spawns a fiber that acquires the semaphore,
   *   runs `feedUpdate`, and releases on completion.
   *
   * Spawned fibers are tracked in `$this->handleUpdateTasks` keyed by
   * `spl_object_id` so a future `Future::cancel` pass on shutdown (added in
   * the spec but not exposed by amphp v3's `Future` directly) can reap
   * them. The keyed map is also opportunistically purged each round so a
   * long-running polling session doesn't accumulate completed-future
   * references.
   *
   * Handler exceptions are **not** caught here — `ErrorsMiddleware`
   * (wired onto every observer at Dispatcher construction) already does
   * the catch and either invokes the error observer or re-raises. A
   * truly uncaught exception will surface to the awaiter of the spawned
   * fiber's Future or, in serial mode, terminate the polling loop. The
   * latter matches upstream's `_process_update` semantics, where the
   * Exception logger swallows everything inside the inner try.
   */
  public function pollingFor(Bot $bot, PollingOptions $options): void
  {
    $concurrency = $options->handleAsTasks;
    // Narrow `int|null` to `positive-int|null` for LocalSemaphore. The
    // PollingOptions constructor already enforces `>= 1 or null`, but
    // PHPStan can't read that invariant without the local guard.
    $semaphore = $concurrency !== null && $concurrency >= 1
      ? new LocalSemaphore($concurrency)
      : null;

    foreach ($this->listenUpdates($bot, $options) as $update) {
      if ($semaphore === null) {
        // Serial dispatch: every handler runs to completion before the
        // next Update is consumed. Exceptions terminate the polling loop;
        // upstream's catch-and-log lives in ErrorsMiddleware, not here.
        $this->feedUpdate($bot, $update);

        continue;
      }

      // Concurrent dispatch: the semaphore caps in-flight work at
      // `handleAsTasks`. Acquire BEFORE spawning so back-pressure flows
      // back to listenUpdates — the loop suspends here when the pool is
      // saturated, which in turn delays the next getUpdates round.
      $lock = $semaphore->acquire();

      $task = async(function () use ($bot, $update, $lock): void {
        try {
          $this->feedUpdate($bot, $update);
        } finally {
          $lock->release();
        }
      });

      $taskId = spl_object_id($task);
      $this->handleUpdateTasks[$taskId] = $task;
      $task->finally(function () use ($taskId): void {
        unset($this->handleUpdateTasks[$taskId]);
      });
    }
  }

  /**
   * Spawn polling for one or more bots. Returns a Future that resolves
   * when every per-bot polling fiber has finished — i.e. after
   * `stopPolling()` (or a SIGTERM/SIGINT) has fired and the loops have
   * drained their current round.
   *
   * The spec mandates `(PollingOptions $options, Bot ...$bots)` order
   * because PHP forbids any parameter following a variadic — the mission
   * brief's `(Bot $bot, ..., Bot ...$additionalBots)` shape would also
   * trigger a "Variadic parameter must be the last parameter" parse error.
   *
   * Concurrency contract:
   * 1. The `$runningLock` mutex serialises the `isPolling` check / set so
   *    a second concurrent `startPolling` call sees the flag and raises
   *    `LogicException`. Mirrors upstream `async with self._running_lock:`
   *    at `dispatcher.py:558`.
   * 2. `emitStartup` and `emitShutdown` are called once each, around the
   *    fan-out, with `bots => $bots` injected per spec § "Polling loop".
   *    The shutdown closes every bot's session as a final cleanup step.
   * 3. Per-bot polling fibers are spawned via `Amp\async`; the returned
   *    Future awaits all of them (success or first error).
   *
   * @return Future<void>
   *
   * @throws LogicException if polling is already in progress on this Dispatcher.
   */
  public function startPolling(PollingOptions $options, Bot ...$bots): Future
  {
    if ($bots === []) {
      throw new LogicException('startPolling: at least one Bot must be supplied');
    }

    $lock = $this->runningLock->acquire();

    try {
      if ($this->isPolling) {
        throw new LogicException('startPolling: polling already in progress on this Dispatcher');
      }
      $this->isPolling = true;
      $this->stopSignal = new DeferredFuture();
    } finally {
      $lock->release();
    }

    // Fire emitStartup BEFORE spawning fibers so a startup handler can
    // raise to abort the whole launch (the LogicException bubbles up to
    // the caller without ever entering the polling loop). The `bots` key
    // mirrors upstream's `dispatcher.py:588` injection.
    $startupKwargs = [
      ...$this->workflowData,
      'bots' => $bots,
      'dispatcher' => $this,
    ];
    $this->emitStartup($startupKwargs);

    // Spawn one polling fiber per bot. The returned Future from
    // `async(...)` carries the inner closure's exceptions, which `await`
    // re-raises here — ErrorsMiddleware should already have swallowed
    // user-level errors so anything reaching this point is a framework
    // bug worth surfacing.
    $polls = [];

    foreach ($bots as $bot) {
      $polls[] = async(function () use ($bot, $options): void {
        $this->pollingFor($bot, $options);
      });
    }

    /** @var Future<void> $awaiter */
    $awaiter = async(function () use ($polls, $bots, $startupKwargs): void {
      try {
        await($polls);
      } finally {
        try {
          $this->emitShutdown($startupKwargs);
        } finally {
          // Close every bot's session — equivalent to upstream's
          // `asyncio.gather(*(bot.session.close() for bot in bots))`
          // at `dispatcher.py:629`. Failures are not aggregated (PHP
          // has no native equivalent of asyncio.gather's exception
          // group); the first failing close propagates and the rest
          // happen on next GC via amphp's destruct paths.
          foreach ($bots as $bot) {
            $bot->session->close();
          }
        }

        // Final state reset under the lock so a follow-up startPolling
        // sees a clean slate. The reset itself is not racy with another
        // stopPolling because the stopSignal future is already complete
        // by the time we reach this finally — but we still take the
        // lock for symmetry with the entry critical section.
        $lock = $this->runningLock->acquire();

        try {
          $this->isPolling = false;
          $this->stopSignal = null;
          $this->handleUpdateTasks = [];
        } finally {
          $lock->release();
        }
      }
    });

    return $awaiter;
  }

  /**
   * Synchronous polling driver — awaits the Future returned by
   * `startPolling` and installs SIGTERM / SIGINT handlers that resolve
   * the shared stop signal. Mirrors upstream `run_polling`
   * (`dispatcher.py:632-684`).
   *
   * Signal handling is best-effort: `EventLoop::onSignal` requires the
   * `pcntl` extension at minimum, and may throw
   * `UnsupportedFeatureException` on Windows or in PHP builds without
   * pcntl. We swallow the throw silently — parity with upstream's
   * `with suppress(NotImplementedError):` block.
   */
  public function runPolling(PollingOptions $options, Bot ...$bots): void
  {
    $signalIds = $this->installSignalHandlers();

    try {
      $this->startPolling($options, ...$bots)->await();
    } finally {
      foreach ($signalIds as $id) {
        try {
          EventLoop::cancel($id);
        } catch (Throwable) {
          // EventLoop::cancel is idempotent and shouldn't throw for a
          // known id, but defensively guard in case the loop has
          // already been torn down (e.g. by a parallel test reset).
        }
      }
    }
  }

  /**
   * Signal the polling loop to stop. Safe to call from any fiber, from a
   * signal handler, or from the main thread (the latter only meaningful
   * outside the loop — but calling it then is harmless since the
   * stopSignal will simply be `null`).
   *
   * The mutex round-trip is the canonical way to read/mutate the
   * `$stopSignal` slot without a torn read between concurrent fibers
   * (e.g. a SIGTERM handler firing while a user-level `stopPolling` call
   * is mid-flight).
   *
   * Idempotent: multiple calls collapse to a single complete() because
   * `DeferredFuture::complete()` would otherwise throw on a second call.
   */
  public function stopPolling(): void
  {
    $lock = $this->runningLock->acquire();

    try {
      if ($this->stopSignal !== null && !$this->stopSignal->isComplete()) {
        $this->stopSignal->complete(null);
      }
    } finally {
      $lock->release();
    }
  }

  /**
   * Register SIGTERM + SIGINT handlers that resolve `$stopSignal`. Returns
   * the event-loop callback ids so `runPolling` can cancel them on exit
   * (otherwise the loop holds a reference that prevents shutdown of the
   * fresh driver in tests).
   *
   * `EventLoop::onSignal` throws `UnsupportedFeatureException` when the
   * pcntl extension is unavailable (Windows, some minimal PHP CLIs).
   * That's exactly upstream's NotImplementedError swallow case at
   * `dispatcher.py:572`; we mirror by skipping silently.
   *
   * @return list<string>
   */
  private function installSignalHandlers(): array
  {
    if (!extension_loaded('pcntl')) {
      return [];
    }

    $ids = [];
    $handler = fn() => $this->stopPolling();

    foreach ([\SIGTERM, \SIGINT] as $sig) {
      try {
        $ids[] = EventLoop::onSignal($sig, $handler);
      } catch (UnsupportedFeatureException) {
        // Loop driver doesn't support signal handling — give up on the
        // remaining handlers too, since they'd all fail the same way.
        return $ids;
      }
    }

    return $ids;
  }
}
