<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Dispatcher\Middlewares\ErrorsMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\UserContextMiddleware;
use Gruven\PhpBotGram\Exceptions\UpdateTypeLookupException;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Update;

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

  public function __construct(?string $name = null)
  {
    parent::__construct($name);

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
}
