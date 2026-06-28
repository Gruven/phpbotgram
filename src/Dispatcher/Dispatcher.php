<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\Sync\LocalMutex;
use Amp\Sync\LocalSemaphore;
use BadMethodCallException;
use Generator;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\ErrorsMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\UserContextMiddleware;
use Gruven\PhpBotGram\Exceptions\RestartingTelegram;
use Gruven\PhpBotGram\Exceptions\TelegramApiException;
use Gruven\PhpBotGram\Exceptions\TelegramNetworkException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Exceptions\UpdateTypeLookupException;
use Gruven\PhpBotGram\Fsm\FsmContextMiddleware;
use Gruven\PhpBotGram\Fsm\FsmStrategy;
use Gruven\PhpBotGram\Fsm\Storage\BaseEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\SimpleEventIsolation;
use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Unspecified;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Utils\Backoff;
use LogicException;
use Revolt\EventLoop;
use Revolt\EventLoop\FiberLocal;
use Revolt\EventLoop\UnsupportedFeatureException;
use RuntimeException;
use Throwable;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\await;
use function Amp\Future\awaitFirst;

/**
 * Root router with polling/webhook entry points — port of
 * `aiogram.dispatcher.dispatcher.Dispatcher`.
 *
 * Extends `Router` with three responsibilities:
 *
 * 1. **Default middleware wiring**. The constructor populates
 *    `$dispatcherMiddlewares` with `UserContextMiddleware` (first) and
 *    `ErrorsMiddleware` (second). `feedUpdate` composes the chain around
 *    its terminal `propagateEvent` call **once** per ingress, so a
 *    multi-router tree never sees these middlewares re-wrapped at each
 *    `propagateEvent` recursion. Order matters: the user-context
 *    middleware injects the `event_context` / `event_from_user` /
 *    `event_chat` / `event_thread_id` kwargs *before* `ErrorsMiddleware`
 *    runs, so an error handler that catches a handler exception sees the
 *    same context shape any other observer would.
 *
 *    The error observer is left untouched at construction — observers
 *    own their per-event inner / outer chains; the dispatcher-level
 *    chain runs above them at the ingress.
 *
 * 2. **Update ingress entry points**. `feedUpdate` is the canonical
 *    synchronous dispatch; `feedRawUpdate` deserialises a wire-shaped
 *    payload via `Serializer::load` first; `feedWebhookUpdate` is the
 *    HTTP-webhook variant — it runs the dispatch chain inside a 55-second
 *    budget (`WEBHOOK_TIMEOUT_SECONDS`), surfaces an in-time
 *    `TelegramMethod` as the inline response, and routes a late-arriving
 *    method through `silentCallRequest` so the bot still issues the API
 *    call. The deadline is configurable per-instance via the constructor
 *    for tests that need a tight value.
 *
 * 3. **Webhook fall-through `silentCallRequest`**. Public **instance**
 *    method (deviation from upstream's `@classmethod` for testability —
 *    see `RecordingDispatcher` in tests/Support). Default behaviour is
 *    `$bot($method)`; subclasses override to capture invocations or
 *    suppress side effects under test.
 *
 * Spec deviations from upstream:
 *
 * - **No synthetic 'update' observer.** Upstream attaches every middleware
 *   to a single `self.update` observer and routes inside `_listen_update`.
 *   The port stores the dispatcher-level chain on a private
 *   `$dispatcherMiddlewares` list and wraps it around `propagateEvent`
 *   inside `feedUpdate`, achieving the same wire shape (middleware wraps
 *   the whole tree exactly once) without an extra synthetic observer.
 * - **FSM auto-wiring.** When `$disableFsm` is `false` (the default) the
 *   constructor builds a `FsmContextMiddleware` from the supplied (or
 *   defaulted) `$storage` / `$fsmStrategy` / `$eventsIsolation` parameters
 *   and registers it as an outer middleware on every Telegram observer
 *   except `error`. Pass `disableFsm: true` to skip this wiring entirely
 *   (useful for bots that have no state-gated handlers).
 * - **`Bot::setCurrent` instead of `with bot.context():`.** PHP's FiberLocal
 *   (via `Revolt\EventLoop\FiberLocal`) is the closest analogue to Python's
 *   `contextvars`. The try/finally guard ensures the binding is unset even
 *   when the dispatch raises.
 */
class Dispatcher extends Router
{
  /**
   * Webhook response deadline in seconds. Mirrors upstream's
   * `feed_webhook_update(_timeout=55, ...)` default at
   * `dispatcher.py:440`. Telegram closes the webhook connection at 60
   * seconds, so 55s gives ~5s of headroom for the HTTP write-back.
   *
   * Exposed as a `public const` so subclasses, tests, and webhook
   * adapters can read the canonical value without instantiating the
   * Dispatcher. The per-instance constructor argument overrides this
   * for tests that need a tight deadline; production code should use
   * the default.
   */
  public const float WEBHOOK_TIMEOUT_SECONDS = 55.0;

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
   * Sticky "polling has been started at least once" flag set inside
   * `startPolling` (after the single-instance guard accepts) and never
   * cleared. Lets `stopPolling` distinguish "never started → throw" from
   * "already cleanly stopped → no-op". Mirrors upstream's reliance on
   * `_stop_signal` and `_stopped_signal` staying non-None after the
   * first start (`dispatcher.py:559-562`'s `if self._stop_signal is None`
   * guard — the slots only get None'd once, at construction).
   */
  private bool $hasEverStarted = false;

  /**
   * Fiber-local "we're inside a polling fiber" flag. Set to `true` at the
   * entry of `pollingFor` and inside each per-update async closure in
   * concurrent mode; cleared via try/finally on exit. `stopPolling`
   * inspects the flag: when `true`, the drain await is **skipped** —
   * awaiting the drain from inside the polling fiber would deadlock
   * because the drain only completes when the polling fiber exits.
   *
   * Without this signal, callers from a handler (which runs inside the
   * polling fiber) would have to either:
   * - Skip stopPolling entirely (breaks tests that need to terminate
   *   the loop from a handler).
   * - Spawn `async(static fn => $dispatcher->stopPolling())` AND ensure
   *   the polling fiber yields before its next batch fetch (real-world
   *   polling yields naturally on the HTTP transport; mocks don't).
   *
   * Upstream's `aiogram` sidesteps this differently: it uses
   * `asyncio.wait(return_when=FIRST_COMPLETED)` on the polling tasks
   * together with the stop signal, then **cancels** the pending polling
   * tasks. The cancellation interrupts the handler's awaited
   * `stop_polling()`. The amphp v3 port doesn't have native Future
   * cancellation, so the FiberLocal pattern is the closest equivalent.
   *
   * Initialised once per Dispatcher instance; the `bool` default models
   * "no value set yet" (≡ false at the call site).
   *
   * @var FiberLocal<bool>
   */
  private readonly FiberLocal $insidePollingFiber;

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
   * Drain barrier completed once the polling awaiter's finally block has
   * fully unwound (emitShutdown + bot session close + final state reset).
   * `stopPolling()` awaits this future OUTSIDE the runningLock so callers
   * observe a fully-drained dispatcher on return.
   *
   * Mirrors upstream `self._stopped_signal: Event` at `dispatcher.py:102`;
   * `stop_polling` calls `await self._stopped_signal.wait()` at
   * `dispatcher.py:509` after setting `_stop_signal`. The port uses a
   * DeferredFuture because amphp v3 has no "Event"-equivalent primitive
   * and the carried value is unused — only the completed status matters.
   *
   * Lifecycle: created together with `$stopSignal` at startPolling entry;
   * completed inside the awaiter's finally block right after the lock-
   * protected state reset (so by the time `getFuture()->await()` resolves
   * in another fiber, $isPolling is false and the session is closed).
   *
   * @var ?DeferredFuture<null>
   */
  private ?DeferredFuture $drainedSignal = null;

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

  /**
   * Per-instance webhook deadline in seconds. Defaults to
   * `self::WEBHOOK_TIMEOUT_SECONDS` (55.0) when the constructor argument
   * is omitted. Tests tighten this to e.g. 0.05s so the slow-handler
   * branch can be exercised without sleeping for nearly a minute.
   *
   * Stored as a positive float; the constructor accepts a nullable
   * argument so callers that don't care can pass nothing instead of
   * having to reference the constant. PHPStan reads the readonly modifier
   * and enforces single-assignment in the ctor body.
   */
  private readonly float $webhookTimeoutSeconds;

  /**
   * Dispatcher-level middleware chain wrapped around `propagateEvent`
   * inside `feedUpdate` once per ingress. Mirrors upstream's
   * `self.update.outer_middleware([...])` at `dispatcher.py:80-84`
   * which composes the same chain around a single synthetic 'update'
   * observer.
   *
   * The port wires the chain here (instead of on every observer) to fix
   * the C1 regression: with per-observer wiring, `Router::propagateEvent`
   * recursing through sub-routers wrapped each `trigger()` call with the
   * same middleware AGAIN — `UserContextMiddleware::resolveContext()`
   * fired twice for a 2-router tree and `ErrorsMiddleware` caught each
   * handler exception twice.
   *
   * Order: `UserContextMiddleware` first so subsequent links see the
   * canonical `event_context` keys populated; `ErrorsMiddleware` second
   * so its catch wraps user-context resolution.
   *
   * @var list<BaseMiddleware>
   */
  private array $dispatcherMiddlewares;

  /**
   * The FSM context middleware auto-wired at construction time.
   *
   * Non-null when FSM is enabled (`$disableFsm = false`); `null` when FSM
   * is disabled. Exposed so callers can call `$dispatcher->fsm->close()`
   * directly (or read FSM options in tests). Mirrors upstream's
   * `self.fsm: FSMContextMiddleware` property at `dispatcher.py:105`.
   *
   * Use `$dispatcher->storage()` as a shorthand for the storage accessor.
   */
  public readonly ?FsmContextMiddleware $fsm;

  public function __construct(
    ?string $name = null,
    ?float $webhookTimeoutSeconds = null,
    // FSM wiring (Phase 5):
    ?BaseStorage $storage = null,
    FsmStrategy $fsmStrategy = FsmStrategy::UserInChat,
    ?BaseEventIsolation $eventsIsolation = null,
    bool $disableFsm = false,
  ) {
    parent::__construct($name);
    $this->runningLock = new LocalMutex();
    $this->webhookTimeoutSeconds = $webhookTimeoutSeconds ?? self::WEBHOOK_TIMEOUT_SECONDS;
    // FiberLocal default false — `stopPolling` reads the slot to decide
    // whether to skip the drain await. Each polling fiber sets it to true
    // for the duration of its loop body. See `$insidePollingFiber`'s
    // docblock for the rationale.
    $this->insidePollingFiber = new FiberLocal(static fn(): bool => false);

    // ErrorsMiddleware needs a closure that re-enters `propagateEvent`
    // with the synthetic ErrorEvent so a registered error observer can
    // claim the failure. The closure captures $this; PHP binds it
    // automatically so the call site stays terse.
    $errorsTrigger = fn(string $type, object $event, array $data): mixed => $this->propagateEvent($type, $event, $data);

    // Wire the dispatcher-level chain once. `feedUpdate` composes it
    // around the terminal `propagateEvent` call before each dispatch.
    // The list is left mutable (no `readonly`) so `RecordingDispatcher`-
    // style subclasses can inject test instrumentation via Reflection.
    $this->dispatcherMiddlewares = [
      new UserContextMiddleware(),
      new ErrorsMiddleware($errorsTrigger),
    ];

    // FSM auto-wiring. When FSM is enabled, build a FsmContextMiddleware
    // (defaulting to MemoryStorage + SimpleEventIsolation) and register it
    // as outer middleware on every Telegram observer except `error`.
    //
    // Mirrors upstream `Dispatcher.__init__` at `dispatcher.py:58-76`
    // which attaches FSMContextMiddleware to `self.update` observer.
    // The port attaches to each concrete Telegram observer (not a synthetic
    // `update` observer) to match SceneRegistry's per-observer wiring
    // pattern (`SceneRegistry::setupMiddleware`). The `error` observer is
    // excluded — it carries `ErrorEvent`, not a Telegram update, and the
    // FSM context keys it would inject are meaningless there.
    if (!$disableFsm) {
      $storage ??= new MemoryStorage();
      $eventsIsolation ??= new SimpleEventIsolation();
      $this->fsm = new FsmContextMiddleware(
        storage: $storage,
        eventsIsolation: $eventsIsolation,
        strategy: $fsmStrategy,
      );

      foreach ($this->observers as $observerName => $observer) {
        if ($observerName === 'error') {
          continue;
        }

        $observer->outerMiddleware($this->fsm);
      }

      // Close storage + event-isolation on dispatcher shutdown so pooled
      // connections (Redis, Mongo) are released cleanly. Mirrors upstream
      // `dispatcher.py:76` `on_shutdown(fsm.close)`.
      $fsmMiddleware = $this->fsm;
      $this->shutdown->register(static function () use ($fsmMiddleware): void {
        $fsmMiddleware->close();
      });
    } else {
      $this->fsm = null;
    }
  }

  /**
   * Return the FSM storage instance.
   *
   * Mirrors upstream `Dispatcher.storage` property (`dispatcher.py:108`).
   *
   * @throws BadMethodCallException When FSM is disabled (`disableFsm: true`).
   */
  public function storage(): BaseStorage
  {
    if ($this->fsm === null) {
      throw new BadMethodCallException('FSM is disabled on this Dispatcher (disableFsm: true).');
    }

    return $this->fsm->storage;
  }

  /**
   * Top-level synchronous dispatch entry. Resolves the wire update_type
   * from the `Update`, reads the child event slot, binds the bot via
   * `Bot::setCurrent` (FiberLocal), composes the dispatcher-level
   * middleware chain around `propagateEvent`, and dispatches with the
   * merged kwargs bag.
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
   * **Middleware wiring (C1 fix)**: the dispatcher-level chain
   * (`UserContextMiddleware` + `ErrorsMiddleware`) wraps the terminal
   * `propagateEvent` call exactly once. Prior to the fix the chain was
   * attached to every observer at construction, which meant
   * `propagateEvent`'s sub-router recursion re-wrapped each child
   * observer's `trigger()` with the same chain — doubling
   * `resolveContext()` runs and duplicating error handling. Wrapping
   * once at the ingress mirrors upstream's `self.update.wrap_outer_middleware`
   * shape (`dispatcher.py:164-172`).
   *
   * @param array<string, mixed> $kwargs Per-call context (state, fsm_storage, …).
   *
   * @throws UpdateTypeLookupException when the Update has no recognised event slot.
   */
  public function feedUpdate(Bot $bot, Update $update, array $kwargs = []): mixed
  {
    // Fix C3: re-mount the update tree onto the supplied bot when the
    // current binding diverges. Mirrors upstream
    // `aiogram/dispatcher/dispatcher.py:153-161`'s
    // `Update.model_validate(update.model_dump(), context={"bot": bot})`
    // round-trip — every nested TelegramObject's `bot` slot must reference
    // the dispatching bot so handler shortcuts (`Message::answer`,
    // `CallbackQuery::answer`, …) call out via the right Bot instance.
    //
    // Identity-skip when bots already match: callers passing a pre-bound
    // Update (the common case from `feedRawUpdate` / `feedWebhookUpdate`)
    // pay nothing for the check beyond a single `!==` comparison.
    if ($update->bot !== $bot) {
      $update = $update->withBot($bot);
    }
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

      // Terminal: propagate through router tree. Closure-typed so the
      // middleware chain can compose around it via the standard
      // `(handler, event, data)` contract.
      $terminal = fn(object $e, array $data): mixed => $this->propagateEvent($updateType, $e, $data);

      // Compose the dispatcher-level chain ONCE. `array_reverse` so the
      // first registered middleware ends up outermost in the final
      // wrapped closure — matches `MiddlewareManager::wrap` semantics.
      $chain = $terminal;

      foreach (array_reverse($this->dispatcherMiddlewares) as $middleware) {
        $next = $chain;
        $chain = static fn(object $e, array $data): mixed => $middleware($next, $e, $data);
      }

      return $chain($event, $merged);
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

    return $this->feedUpdate($bot, $update, [
      ...$kwargs,
      'raw_update' => $rawUpdate,
    ]);
  }

  /**
   * Webhook variant — runs the dispatcher chain inside the 55-second
   * webhook deadline (configurable per Dispatcher via the constructor)
   * and surfaces either a `TelegramMethod` (inline response) or `null`
   * (empty response). Port of `aiogram.Dispatcher.feed_webhook_update`
   * (`dispatcher.py:436-493`).
   *
   * Two branches:
   *
   * - **In-time**: the chain completes within `WEBHOOK_TIMEOUT_SECONDS`.
   *   If the result is a `TelegramMethod`, return it so the caller (the
   *   webhook HTTP adapter) can encode it as the response body. Any
   *   other return value (string, sentinel, null) collapses to `null` —
   *   the adapter then writes an empty JSON `{}`.
   * - **Deadline expired**: the chain has NOT completed by the time the
   *   55-second timer fires. We emit `trigger_error("Detected slow
   *   response into webhook…", E_USER_WARNING)` (parity with upstream's
   *   `warnings.warn(..., RuntimeWarning)` at `dispatcher.py:462-468`),
   *   attach a continuation that routes any eventual `TelegramMethod`
   *   through `silentCallRequest` (so the side effect still reaches
   *   Telegram via a normal API call), and return `null` immediately so
   *   the webhook adapter doesn't keep the HTTP socket open past the
   *   deadline.
   *
   * **Update|array overload (Fix I3)**: the `$update` parameter accepts
   * either an already-deserialised `Update` instance or a wire-shaped
   * associative array (the typical HTTP body decoded via
   * `json_decode($body, true)`). The array form hydrates via
   * `Serializer::load(Update::class, ...)` before the dispatch runs —
   * mirrors upstream's `dispatcher.py:443-444` overload.
   *
   * Implementation notes:
   *
   * - The race is implemented via `Amp\Future\awaitFirst` against an
   *   `async(fn() => $this->feedUpdate(...))` task and an `async(delay)`
   *   timer. Upstream uses `loop.call_later` + `loop.create_future` to
   *   model the same primitive; awaitFirst is the canonical amphp v3
   *   equivalent and reads cleaner than spinning a manual DeferredFuture.
   * - We deliberately do NOT use `Amp\TimeoutCancellation` here:
   *   cancellation would interrupt the dispatch fiber, but the spec
   *   requires the dispatch to **continue running in the background** so
   *   the fall-through continuation can route any eventual
   *   `TelegramMethod`. The race-with-a-timer pattern preserves the
   *   in-flight fiber.
   * - The timer task uses `ignore()` so a never-awaited timeout future
   *   (the happy path where the dispatch wins) doesn't surface an
   *   "unhandled future" warning at GC time.
   * - The dispatch task's `map()` callback is attached **after** the race
   *   resolves. amphp guarantees the callback fires on completion even if
   *   the future is already complete — but in the timeout branch the
   *   dispatch is still in flight at attachment time. The callback runs
   *   on the same event loop driver that's hosting the dispatch fiber,
   *   so no cross-thread synchronisation is needed.
   *
   * @param array<string, mixed>|Update $update Already-deserialised
   *                                            `Update` or a wire-shaped (snake_case) associative array.
   * @param array<string, mixed> $kwargs
   */
  public function feedWebhookUpdate(Bot $bot, array|Update $update, array $kwargs = []): mixed
  {
    if (is_array($update)) {
      $kwargs = [
        ...$kwargs,
        'raw_update' => $update,
      ];

      // Wire-shape: snake_case associative array. Hydrate via the
      // serializer with bot context so every nested TelegramObject
      // sees `$bot` (parity with upstream's `Update.model_validate(...,
      // context={"bot": bot})`).
      /** @var Update $update */
      $update = Serializer::load(Update::class, $update, $bot);
    }
    $start = hrtime(true);

    /** @var Future<mixed> $dispatchTask */
    $dispatchTask = async(fn(): mixed => $this->feedUpdate($bot, $update, $kwargs));

    // `reference: false` on the inner delay is critical: a referenced
    // delay would keep the event loop alive for the full 55-second
    // budget even when the dispatch wins immediately, which would block
    // the test harness's `EventLoop::run()` from returning. The timer
    // task itself is `ignore()`'d so an unhandled-future warning at GC
    // doesn't surface when the dispatch wins the race.
    /** @var Future<null> $timeoutTask */
    $timeoutTask = async(function (): ?bool {
      delay($this->webhookTimeoutSeconds, reference: false);

      return null;
    })->ignore();

    // Race: whichever future resolves first wins. awaitFirst surfaces
    // exceptions from the winning future (and only the winning future);
    // we want the dispatch task's exceptions to propagate to the caller
    // when it wins, and we tolerate the timer task's never-throws
    // contract.
    //
    // The race itself ignores the winning future's resolved value /
    // exception — we re-inspect `$dispatchTask` afterwards. Suppressing
    // any throw from `awaitFirst` here avoids a brittle re-raise pattern
    // when the inspection below is going to look at `$dispatchTask` state
    // anyway. The timer task `->ignore()`s its own future contract, so
    // the only re-raise source is the dispatch task; we let `await()`
    // surface it under the typed handling below.
    try {
      awaitFirst([$dispatchTask, $timeoutTask]);
    } catch (Throwable) {
      // Swallowed; the typed handling lives in the `isComplete()` branch
      // below so the dispatch-failed path goes through a single re-raise
      // site (which we wrap in a try/catch for the `UpdateTypeLookupException`
      // swallow Fix I1 demands).
    }

    if ($dispatchTask->isComplete()) {
      // Dispatch won (resolved or failed). `await()` re-raises the
      // failure if any. ErrorsMiddleware should already have absorbed
      // user-level handler errors; the typed catch below covers Fix I1
      // (`UpdateTypeLookupException` from a forward-compat Telegram update
      // kind) — anything else is a framework bug we let surface to the
      // webhook adapter.
      try {
        $result = $dispatchTask->await();
      } catch (UpdateTypeLookupException $e) {
        // Fix I1: forward-compat Telegram update kinds (no schema entry
        // yet) surface as `UpdateTypeLookupException` from `feedUpdate`.
        // Upstream's `_listen_update` swallows the typed error and raises
        // `SkipHandler` + `RuntimeWarning` (`dispatcher.py:267-279`); the
        // port emits an `E_USER_WARNING` and returns null so the webhook
        // adapter writes an empty body instead of a 500.
        trigger_error(
          sprintf('feedWebhookUpdate: skipped unknown update type — %s', $e->getMessage()),
          \E_USER_WARNING,
        );

        return null;
      }

      return $result instanceof TelegramMethod ? $result : null;
    }

    // Timeout won. Compute elapsed in seconds for the warning message
    // (hrtime returns nanoseconds; the cast to float preserves sub-ms
    // precision the %.2f format will round to two decimals).
    $elapsed = (hrtime(true) - $start) / 1_000_000_000;
    trigger_error(
      sprintf('Detected slow response into webhook (>%.2fs)', $elapsed),
      \E_USER_WARNING,
    );

    // Attach the fall-through continuation. When the dispatch eventually
    // completes, if its result is a TelegramMethod, route it through
    // silentCallRequest (overrideable in tests via RecordingDispatcher).
    // The map's return value is ignored — we just need the side effect
    // of invoking silentCallRequest at completion time.
    //
    // Fix I1 (deferred path): `catch(UpdateTypeLookupException)` swallows
    // the typed error if the late dispatch completes with one. `map()`'s
    // callback only fires on success; without the `catch()` hook the
    // failure would survive only as a "future errored, ignored" state. The
    // catch returns null so the downstream future settles cleanly. The
    // outer `ignore()` covers any *other* exception type the late dispatch
    // might raise (framework bugs we don't want surfacing at GC time).
    $dispatchTask
      ->map(function (mixed $result) use ($bot): void {
        if ($result instanceof TelegramMethod) {
          $this->silentCallRequest($bot, $result);
        }
      })
      ->catch(static function (Throwable $error): ?bool {
        if ($error instanceof UpdateTypeLookupException) {
          trigger_error(
            sprintf('feedWebhookUpdate: skipped unknown update type — %s', $error->getMessage()),
            \E_USER_WARNING,
          );

          return null;
        }

        throw $error;
      })
      ->ignore();

    return null;
  }

  /**
   * Webhook fall-through: dispatch a method via `$bot($method)` when the
   * inline-response window has closed. Invoked by `feedWebhookUpdate`'s
   * map continuation when the dispatch chain finishes *after* the 55-second
   * deadline and the eventual result is a `TelegramMethod`. Also invoked
   * from `pollingFor` when a handler returns a `TelegramMethod` (the
   * polling-side analogue of the webhook inline response).
   *
   * Public **instance** method (deviation from upstream's `@classmethod`)
   * so tests can override it via `RecordingDispatcher` to capture the
   * fall-through invocations without driving a real network call.
   * Upstream's `unittest.mock.patch` of a class method does not translate
   * cleanly to PHP. See spec § "Webhook response contract" for the
   * rationale.
   *
   * **Return type deviation from spec**: the port returns `mixed`, not
   * `void`. Upstream's `silent_call_request` returns whatever
   * `await bot(result)` resolves to (the `TelegramMethod`'s
   * `ReturnsType`), so a typed `mixed` is faithful to upstream — the
   * spec's `void` was incorrect. Subclasses such as `RecordingDispatcher`
   * lean on the `mixed` to return a sentinel (`null`) without driving the
   * real bot call.
   *
   * **TelegramApiException handling**: a transient API failure from the
   * underlying call (chat gone, message already deleted, bot blocked,
   * etc.) MUST NOT kill the caller. In serial polling the next update
   * is unrelated and the loop should keep going; on the webhook
   * fall-through path the request lifecycle is already over and the
   * failure has nowhere to surface. We mirror upstream's
   * `silent_call_request` (`aiogram/dispatcher/dispatcher.py:294-301`)
   * which catches `TelegramAPIError` and logs — the port emits an
   * `E_USER_WARNING` (the project-wide RuntimeWarning analogue, see also
   * Fix C2 / Fix I1) and returns `null`. Any non-API throwable
   * (programming errors, fiber-level failures) still propagates so the
   * upstream layers / `ErrorsMiddleware` can react.
   *
   * @param TelegramMethod<mixed> $method
   *
   * @return mixed Whatever `$bot($method)` resolves to (the method's
   *               declared `ReturnsType`), or `null` when a
   *               `TelegramApiException` was swallowed. Subclass
   *               overrides may return any value, including `null` to
   *               suppress side effects.
   */
  public function silentCallRequest(Bot $bot, TelegramMethod $method): mixed
  {
    try {
      return $bot($method);
    } catch (TelegramApiException $e) {
      trigger_error(
        sprintf(
          'silentCallRequest: %s failed — %s',
          $method::class,
          $e->getMessage(),
        ),
        \E_USER_WARNING,
      );

      return null;
    }
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

    // Resolve the Unspecified sentinel (Fix I8) when callers drive
    // `listenUpdates` directly — `startPolling` already replaces it, but
    // tests and external callers may pass a stock `PollingOptions()`.
    // Narrow the union to the `null|list<string>` shape `GetUpdates`
    // expects via `instanceof` so PHPStan can see the narrowing.
    $allowedUpdates = $options->allowedUpdates instanceof Unspecified
      ? $this->resolveUsedUpdateTypes()
      : $options->allowedUpdates;

    while ($this->stopSignal === null || !$this->stopSignal->isComplete()) {
      // Yield to the event loop once per round so any pending fibers
      // (e.g. concurrent per-update tasks spawned in `pollingFor`, or
      // a SIGTERM handler waiting to fire `stopPolling`) get a chance
      // to run before we re-enter `getUpdates`. In real polling the
      // HTTP transport's `getUpdates` call itself yields on the network
      // socket, so this explicit `delay(0)` is the mock-test equivalent
      // — it costs one event-loop tick per round, which is negligible
      // compared to the round-trip time of an actual Telegram poll
      // (median ~50ms on long-poll). Without this, the polling fiber
      // would spin through canned responses synchronously in mocks and
      // never let signal-setters or per-update fibers actually run.
      delay(0);

      // Fix I5: pass `$bot->session->timeout + $options->pollingTimeout`
      // as the HTTP transport timeout to `Bot::__invoke($method,
      // $timeout)`. Without this floor a mid-long-poll HTTP timeout
      // (default 60s session timeout) would cut the request short when
      // the long-poll budget (default 10s) is comfortably inside the
      // window. Mirrors upstream `dispatcher.py:216`
      // "request_timeout = int(bot.session.timeout + polling_timeout)".
      $requestTimeout = (int)$bot->session->timeout + $options->pollingTimeout;

      try {
        /** @var list<Update> $updates */
        $updates = $bot(new GetUpdates(
          offset: $offset,
          timeout: $options->pollingTimeout,
          allowedUpdates: $allowedUpdates,
        ), $requestTimeout);
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

    // Fix I3: mark this fiber as "inside the polling loop" so a nested
    // call to `stopPolling` (typically from a handler that wants to
    // terminate the loop) skips the drain await — awaiting drain from
    // inside the polling fiber would deadlock since the drain only
    // completes when the polling fiber exits. See `$insidePollingFiber`'s
    // docblock.
    $this->insidePollingFiber->set(true);

    try {
      foreach ($this->listenUpdates($bot, $options) as $update) {
        // Fix C2: swallow `UpdateTypeLookupException` with a RuntimeWarning so a
        // single unknown update — typically caused by Telegram introducing a new
        // update kind before the schema was regenerated — does NOT kill the
        // long-poll loop. Upstream's `_listen_update` raises `SkipHandler`
        // inside a `try/except UpdateTypeLookupError: warnings.warn(...)` block
        // (`dispatcher.py:267-279`); the port catches the typed exception at
        // the dispatch entry-point instead since we don't run the synthetic
        // `update` observer.
        //
        // Fix I1: in the CONCURRENT branch the typed catch lives INSIDE the
        // async() closure — exceptions thrown by `feedUpdate` running inside
        // the spawned Future would otherwise land on the future state, not
        // this synchronous frame, and surface as an "unhandled future" warning
        // at GC time.
        //
        // Fix I2: capture `feedUpdate`'s return value in BOTH branches. If
        // the handler returned a `TelegramMethod` (the polling-side analogue
        // of webhook's inline response), route it through `silentCallRequest`
        // so the side effect still reaches Telegram. Mirrors upstream
        // `_process_update`'s "if call_answer and isinstance(response,
        // TelegramMethod): await self.silent_call_request(...)" at
        // `dispatcher.py:321-322`.
        try {
          if ($semaphore === null) {
            // Serial dispatch: every handler runs to completion before the
            // next Update is consumed. Exceptions terminate the polling loop;
            // upstream's catch-and-log lives in ErrorsMiddleware, not here.
            $result = $this->feedUpdate($bot, $update);

            if ($result instanceof TelegramMethod) {
              $this->silentCallRequest($bot, $result);
            }

            continue;
          }

          // Concurrent dispatch: the semaphore caps in-flight work at
          // `handleAsTasks`. Acquire BEFORE spawning so back-pressure flows
          // back to listenUpdates — the loop suspends here when the pool is
          // saturated, which in turn delays the next getUpdates round.
          $lock = $semaphore->acquire();

          $task = async(function () use ($bot, $update, $lock): void {
            // Fix I3: each per-update fiber inherits the "inside polling"
            // marker so a handler-side `stopPolling` from a concurrent
            // dispatch fiber also skips the drain await. Set in the new
            // fiber's own FiberLocal slot — Revolt's `FiberLocal` is
            // per-fiber, so the outer `pollingFor` set doesn't propagate
            // automatically.
            $this->insidePollingFiber->set(true);

            try {
              $result = $this->feedUpdate($bot, $update);

              if ($result instanceof TelegramMethod) {
                $this->silentCallRequest($bot, $result);
              }
            } catch (UpdateTypeLookupException $e) {
              // Fix I1: swallow forward-compat unknown update types from
              // the spawned future. Without this catch the exception would
              // become an "unhandled future" warning at GC time and the
              // polling loop would continue blindly. Mirrors the outer
              // serial-branch catch (above) with the same warning shape.
              trigger_error(
                sprintf(
                  'Detected unknown update type, skipping: %s. Telegram may have introduced new update kinds; consider syncing the schema.',
                  $e->getMessage(),
                ),
                \E_USER_WARNING,
              );
            } finally {
              $lock->release();
            }
          });

          $taskId = spl_object_id($task);
          $this->handleUpdateTasks[$taskId] = $task;
          $task->finally(function () use ($taskId): void {
            unset($this->handleUpdateTasks[$taskId]);
          });
        } catch (UpdateTypeLookupException $e) {
          trigger_error(
            sprintf(
              'Detected unknown update type, skipping: %s. Telegram may have introduced new update kinds; consider syncing the schema.',
              $e->getMessage(),
            ),
            \E_USER_WARNING,
          );

          continue;
        }
      }
    } finally {
      // Clear the FiberLocal marker so a future invocation on the same
      // fiber (e.g. test infra reusing the main fiber across polling
      // rounds) sees a clean default.
      $this->insidePollingFiber->set(false);
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
      $this->hasEverStarted = true;
      $this->stopSignal = new DeferredFuture();
      // Fix I3: track the drain barrier alongside the stop signal so
      // `stopPolling` can block the caller until the awaiter's finally
      // block has fully unwound. Mirrors upstream `_stopped_signal: Event`
      // at `dispatcher.py:102`.
      $this->drainedSignal = new DeferredFuture();
    } finally {
      $lock->release();
    }

    // Fix I8: when the caller left `$allowedUpdates` as the Unspecified
    // sentinel, replace it with the auto-resolved list of update types
    // that have at least one registered handler in the router tree.
    // Mirrors upstream `dispatcher.py:564-565`: "if allowed_updates is
    // UNSET: allowed_updates = self.resolve_used_update_types()".
    //
    // The replacement happens on a copy (new PollingOptions instance)
    // because `PollingOptions` is `final readonly` — we cannot mutate
    // the original. Constructing the copy here is cheap and keeps the
    // polling loop's `$options` argument typed as `list<string>|null`,
    // matching `GetUpdates::$allowedUpdates`.
    if ($options->allowedUpdates instanceof Unspecified) {
      $options = new PollingOptions(
        pollingTimeout: $options->pollingTimeout,
        backoffConfig: $options->backoffConfig,
        allowedUpdates: $this->resolveUsedUpdateTypes(),
        handleAsTasks: $options->handleAsTasks,
      );
    }

    // Fire emitStartup BEFORE spawning fibers so a startup handler can
    // raise to abort the whole launch (the LogicException bubbles up to
    // the caller without ever entering the polling loop). The `bots` key
    // mirrors upstream's `dispatcher.py:588` injection.
    //
    // Fix I4: inject `bot` singular = the LAST bot of the variadic list
    // alongside `bots` plural. Mirrors upstream's
    // `await self.emit_startup(bot=bots[-1], **workflow_data)`
    // (`dispatcher.py:595`). Handlers can declare `Bot $bot` to receive
    // a deterministic single-bot reference even when polling fans out
    // over multiple bots — matches aiogram's convention.
    $startupKwargs = [
      ...$this->workflowData,
      'bots' => $bots,
      'bot' => $bots[array_key_last($bots)],
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
        //
        // Fix I3: complete `$drainedSignal` AFTER the state reset but
        // BEFORE nullifying it so a parallel `stopPolling` fiber parked
        // on the await observes a fully-drained dispatcher (isPolling
        // false, stopSignal null, session closed) when it resumes.
        $lock = $this->runningLock->acquire();
        $drained = null;

        try {
          $this->isPolling = false;
          $this->stopSignal = null;
          $this->handleUpdateTasks = [];
          $drained = $this->drainedSignal;
          $this->drainedSignal = null;
        } finally {
          $lock->release();
        }

        if ($drained !== null && !$drained->isComplete()) {
          $drained->complete(null);
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
   * Signal the polling loop to stop, then BLOCK the caller until the
   * polling fibers have actually drained (emitShutdown finished, every
   * bot session closed, internal state reset). Safe to call from any
   * fiber or from a signal handler.
   *
   * Fix I3: contract mirrors upstream's `stop_polling` at
   * `dispatcher.py:497-509`:
   *
   * - **Never started**: no `startPolling` has been seen on this
   *   Dispatcher (`$hasEverStarted === false`). We raise
   *   `RuntimeException("Polling is not started")` — parity with
   *   upstream's `if not self._running_lock.locked(): raise
   *   RuntimeError("Polling is not started")`.
   * - **Active polling**: `$stopSignal` is present. We complete it (if
   *   not already complete — multi-stop is idempotent) and then await
   *   `$drainedSignal` outside the lock so the caller observes a
   *   fully-drained dispatcher on return.
   * - **Already cleanly stopped**: `$hasEverStarted === true &&
   *   $drainedSignal === null` — the dispatcher ran a polling round to
   *   completion before. We return silently (no throw, no await), parity
   *   with upstream's `if not self._stop_signal or not
   *   self._stopped_signal: return` at `dispatcher.py:506-507`.
   *
   * **Inside the polling fiber**: calling `stopPolling` from a handler
   * is supported via the `$insidePollingFiber` FiberLocal flag — the
   * drain await is skipped, so the call completes the stop signal
   * synchronously and returns immediately. The polling fiber's
   * post-yield check then unwinds the loop. Without the flag the call
   * would deadlock (the drain only completes when the polling fiber
   * exits, but the polling fiber would be parked here).
   *
   * The mutex round-trip is the canonical way to read/mutate the
   * `$stopSignal` slot without a torn read between concurrent fibers
   * (e.g. a SIGTERM handler firing while a user-level `stopPolling` call
   * is mid-flight). The drain await happens OUTSIDE the lock so a stop
   * fiber doesn't deadlock against the awaiter's finally block (which
   * itself acquires the lock to reset state).
   *
   * @throws RuntimeException when no `startPolling` has ever been called
   *                          on this Dispatcher.
   */
  public function stopPolling(): void
  {
    $lock = $this->runningLock->acquire();
    $drainSignal = null;

    try {
      if (!$this->hasEverStarted) {
        throw new RuntimeException('Polling is not started');
      }

      if ($this->stopSignal !== null && !$this->stopSignal->isComplete()) {
        $this->stopSignal->complete(null);
      }
      $drainSignal = $this->drainedSignal;
    } finally {
      $lock->release();
    }

    // Skip the drain await when we're inside the polling fiber chain
    // (a handler called us): the drain only completes when the polling
    // fiber exits, but the polling fiber is the one parked here. The
    // post-yield check in `listenUpdates` will catch the stop signal we
    // just set and unwind the loop cleanly. External callers (different
    // fiber) see `false` and wait for the drain normally.
    if ($this->insidePollingFiber->get()) {
      return;
    }

    // Wait for the polling fibers to actually finish (outside the lock
    // to avoid a deadlock against the awaiter's finally block which also
    // acquires the runningLock for the state reset). `null` drain signal
    // means the polling round already drained — return silently.
    $drainSignal?->getFuture()->await();
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
