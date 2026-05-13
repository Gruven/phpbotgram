<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\ErrorsMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\EventContext;
use Gruven\PhpBotGram\Dispatcher\Middlewares\UserContextMiddleware;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Exceptions\UpdateTypeLookupException;
use Gruven\PhpBotGram\Methods\GetMe;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\ErrorEvent;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

/**
 * Ports `aiogram/dispatcher/dispatcher.py::Dispatcher`.
 *
 * The dispatcher is the polling/webhook entry point — it extends `Router`,
 * wires the default outer-middleware stack (`UserContextMiddleware` first,
 * `ErrorsMiddleware` second), and exposes `feedUpdate` / `feedRawUpdate` /
 * `feedWebhookUpdate` as top-level ingress for incoming updates. The 55s
 * webhook deadline and the slow-response warning live in Task 3.13 — Task
 * 3.10 covers only the synchronous dispatch path.
 *
 * @internal
 *
 * @coversNothing
 */
final class DispatcherTest extends TestCase
{
  use RunAsyncTrait;

  protected function tearDown(): void
  {
    // Defensively clear the FiberLocal `current bot` slot between tests so a
    // failed feedUpdate (which sets and unsets it via try/finally) cannot
    // leak the binding into a later test.
    Bot::setCurrent(null);
  }

  public function testDispatcherExtendsRouter(): void
  {
    // Spec § "Dispatcher, Router, Filters": `Dispatcher extends Router` so
    // it inherits the per-update-type observers and tree composition. The
    // root entry-point semantics are added on top.
    $dispatcher = new Dispatcher();

    self::assertInstanceOf(Router::class, $dispatcher);
  }

  public function testWorkflowDataStartsEmpty(): void
  {
    // The workflow data bag is a mutable associative array merged into every
    // handler invocation's kwargs. Default is empty so callers can add their
    // own keys via direct property mutation.
    $dispatcher = new Dispatcher();

    self::assertSame([], $dispatcher->workflowData);
  }

  public function testDispatcherMiddlewareWiredOnceAtFeedUpdateLayer(): void
  {
    // Fix C1: the dispatcher-level middleware (UserContextMiddleware first,
    // ErrorsMiddleware second) is wired ONCE at the feedUpdate entry layer
    // — NOT on every observer. Upstream achieves the same shape with a
    // single synthetic 'update' observer (`dispatcher.py:72-84`); the port
    // stores the chain on a private `dispatcherMiddlewares` property and
    // wraps it around `propagateEvent` once per `feedUpdate` call.
    //
    // The motivation is C1: wiring on every observer caused the chain to
    // wrap each invocation again when `propagateEvent` recurses through
    // sub-routers, doubling `UserContextMiddleware::resolveContext()` runs
    // and duplicating exception handling in `ErrorsMiddleware`.
    $dispatcher = new Dispatcher();

    foreach ($dispatcher->observers as $name => $observer) {
      // After the fix, observers do NOT carry the dispatcher-level chain
      // themselves — neither the 25 update observers nor the errors
      // observer should have anything pre-installed at construction time.
      self::assertCount(
        0,
        $observer->outerMiddleware,
        "Observer '{$name}' must NOT carry per-observer dispatcher middleware (C1 fix).",
      );
      self::assertCount(0, $observer->innerMiddleware, "Observer '{$name}' must start with empty inner middleware.");
    }

    $reflection = new ReflectionProperty(Dispatcher::class, 'dispatcherMiddlewares');

    /** @var list<BaseMiddleware> $chain */
    $chain = $reflection->getValue($dispatcher);

    self::assertCount(2, $chain, 'Dispatcher must wire two middlewares at the feedUpdate layer.');
    self::assertInstanceOf(UserContextMiddleware::class, $chain[0]);
    self::assertInstanceOf(ErrorsMiddleware::class, $chain[1]);
  }

  public function testMultiRouterDoesNotDoubleRunDispatcherMiddleware(): void
  {
    // Fix C1 regression: when the root dispatcher includes a child router
    // and the matching handler is registered on the child, the
    // dispatcher-level outer middleware (UserContextMiddleware +
    // ErrorsMiddleware) MUST run exactly once. Before the fix the
    // middleware was wired onto every observer, and `propagateEvent`
    // recursing into the child observer wrapped each invocation with the
    // same middleware AGAIN — `resolveContext()` fired twice and a thrown
    // handler exception walked through `ErrorsMiddleware` twice.
    //
    // We instrument by counting `UserContextMiddleware`-style runs: a
    // spy middleware on the dispatcher-level chain increments a counter on
    // each entry. The dispatcher MUST trigger it exactly once per
    // feedUpdate, regardless of the depth at which the handler claims
    // the event.
    $dispatcher = new Dispatcher();
    $child = new Router('child');
    $dispatcher->includeRouter($child);
    $child->message->register(static fn(): string => 'claimed-by-child');

    $counter = 0;
    $spy = new class ($counter) extends BaseMiddleware {
      public function __construct(public int &$counter) {}
      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        ++$this->counter;

        return $handler($event, $data);
      }
    };

    // The fixed Dispatcher exposes `dispatcherMiddlewares` so we can
    // sneak in a counting middleware that runs at the same wrapping
    // layer as UserContextMiddleware / ErrorsMiddleware.
    $reflection = new ReflectionProperty(Dispatcher::class, 'dispatcherMiddlewares');

    /** @var list<BaseMiddleware> $current */
    $current = $reflection->getValue($dispatcher);
    $reflection->setValue($dispatcher, [...$current, $spy]);

    $result = $dispatcher->feedUpdate(new MockedBot(), self::messageUpdate('hi'));

    self::assertSame('claimed-by-child', $result);
    self::assertSame(1, $counter, 'Dispatcher middleware must run exactly once across the router tree.');
  }

  public function testFeedUpdateDispatchesMessageToObserver(): void
  {
    // Happy path: an Update carrying a Message resolves to update_type
    // 'message', and the message observer's registered handler claims the
    // event. The dispatcher returns the handler's return value verbatim.
    $dispatcher = new Dispatcher();
    $dispatcher->message->register(static fn(): string => 'claimed');

    $update = self::messageUpdate('hi');

    $result = $dispatcher->feedUpdate(new MockedBot(), $update);

    self::assertSame('claimed', $result);
  }

  public function testFeedUpdateDispatchesCallbackQueryToObserver(): void
  {
    // Non-message dispatch: a callback_query carrying Update routes through
    // the callback_query observer (not the message observer). Verifies the
    // SCHEMA_FIELD_FOR_TYPE map correctly translates 'callback_query' to
    // `Update::$callbackQuery`.
    $dispatcher = new Dispatcher();
    $messageSeen = false;
    $callbackSeen = false;
    $dispatcher->message->register(static function () use (&$messageSeen): string {
      $messageSeen = true;

      return 'message';
    });
    $dispatcher->callbackQuery->register(static function () use (&$callbackSeen): string {
      $callbackSeen = true;

      return 'callback';
    });

    $update = new Update(
      updateId: 1,
      callbackQuery: new CallbackQuery(id: 'cb', fromUser: self::makeUser(), chatInstance: 'ci', data: 'click'),
    );

    $result = $dispatcher->feedUpdate(new MockedBot(), $update);

    self::assertSame('callback', $result);
    self::assertFalse($messageSeen, 'Message observer must not run when update is callback_query.');
    self::assertTrue($callbackSeen);
  }

  public function testFeedUpdateThrowsForEmptyUpdate(): void
  {
    // An Update with every optional slot null has no event type → upstream
    // raises UpdateTypeLookupError, the port mirrors with the typed
    // UpdateTypeLookupException. The update_id surfaces in the message so
    // logs can correlate.
    $dispatcher = new Dispatcher();
    $update = new Update(updateId: 7);

    $this->expectException(UpdateTypeLookupException::class);
    $this->expectExceptionMessageMatches('/7/');

    $dispatcher->feedUpdate(new MockedBot(), $update);
  }

  public function testFeedUpdateInjectsBotEventUpdateIntoHandlerKwargs(): void
  {
    // Spec § "Injected dispatcher kwargs": every handler invocation sees
    // `bot` and `event_update` injected. Verifies the merge into kwargs
    // before propagateEvent runs.
    $dispatcher = new Dispatcher();
    $observed = [];
    $dispatcher->message->register(static function (
      Bot $bot,
      Update $event_update,
    ) use (&$observed): string {
      $observed = ['bot' => $bot, 'event_update' => $event_update];

      return 'ok';
    });
    $bot = new MockedBot();
    $update = self::messageUpdate('hi');

    $dispatcher->feedUpdate($bot, $update);

    self::assertSame($bot, $observed['bot']);
    self::assertSame($update, $observed['event_update']);
  }

  public function testFeedUpdateInjectsWorkflowDataIntoHandlerKwargs(): void
  {
    // The dispatcher's workflowData bag is merged into every handler call.
    // Keys live alongside the per-call $kwargs argument; per-call kwargs win
    // on key collision (last-wins via PHP's spread merge order).
    $dispatcher = new Dispatcher();
    $dispatcher->workflowData = ['db' => 'mysql', 'shared' => 'workflow'];
    $observed = null;
    $dispatcher->message->register(static function (string $db, string $shared) use (&$observed): string {
      $observed = ['db' => $db, 'shared' => $shared];

      return 'ok';
    });

    $dispatcher->feedUpdate(new MockedBot(), self::messageUpdate('hi'));

    self::assertSame(['db' => 'mysql', 'shared' => 'workflow'], $observed);
  }

  public function testFeedUpdatePerCallKwargsOverrideWorkflowData(): void
  {
    // When a caller-supplied $kwargs key matches a workflowData key, the
    // per-call value wins. Matches the spec's merge order: workflowData
    // first, then $kwargs.
    $dispatcher = new Dispatcher();
    $dispatcher->workflowData = ['db' => 'workflow_default'];
    $observed = null;
    $dispatcher->message->register(static function (string $db) use (&$observed): string {
      $observed = $db;

      return 'ok';
    });

    $dispatcher->feedUpdate(new MockedBot(), self::messageUpdate('hi'), ['db' => 'per_call_override']);

    self::assertSame('per_call_override', $observed);
  }

  public function testFeedUpdateBindsCurrentBotInsideHandler(): void
  {
    // Mirrors upstream `with bot.context():`. Inside a handler, `Bot::current()`
    // returns the bot the dispatcher is dispatching for. Outside (post-call),
    // it returns null — the try/finally unsets it.
    $dispatcher = new Dispatcher();
    $insideValue = null;
    $dispatcher->message->register(static function () use (&$insideValue): string {
      $insideValue = Bot::current();

      return 'ok';
    });
    $bot = new MockedBot();

    self::assertNull(Bot::current(), 'Pre-call sanity: no current bot.');
    $dispatcher->feedUpdate($bot, self::messageUpdate('hi'));

    self::assertSame($bot, $insideValue);
    self::assertNull(Bot::current(), 'Post-call current bot must be reset to null.');
  }

  public function testFeedUpdateUnsetsCurrentBotEvenOnException(): void
  {
    // try/finally invariant: if a handler throws (or any later step raises),
    // Bot::current() still returns null after feedUpdate returns. Otherwise
    // a poisoned binding would leak into the next dispatch.
    $dispatcher = new Dispatcher();
    $dispatcher->message->register(static function (): never {
      throw new RuntimeException('handler bomb');
    });
    $bot = new MockedBot();

    try {
      $dispatcher->feedUpdate($bot, self::messageUpdate('hi'));
      self::fail('Expected RuntimeException to propagate.');
    } catch (RuntimeException $e) {
      self::assertSame('handler bomb', $e->getMessage());
    }

    self::assertNull(Bot::current(), 'current bot must be unset even after handler throws.');
  }

  public function testFeedRawUpdateLoadsViaSerializerAndDispatches(): void
  {
    // feedRawUpdate is the convenience wrapper that serializer-deserialises
    // a raw payload to an Update then calls feedUpdate. Verifies the wire-
    // shaped JSON (snake_case keys) is correctly converted.
    $dispatcher = new Dispatcher();
    $observed = null;
    $dispatcher->message->register(static function (Update $event_update) use (&$observed): string {
      $observed = $event_update;

      return 'handled';
    });

    $rawUpdate = [
      'update_id' => 99,
      'message' => [
        'message_id' => 1,
        'date' => 0,
        'chat' => ['id' => 5, 'type' => 'private'],
        'text' => 'hi',
      ],
    ];

    $result = $dispatcher->feedRawUpdate(new MockedBot(), $rawUpdate);

    self::assertSame('handled', $result);
    self::assertInstanceOf(Update::class, $observed);
    self::assertSame(99, $observed->updateId);
    self::assertNotNull($observed->message);
    self::assertSame('hi', $observed->message->text);
  }

  // Note: feedWebhookUpdate contract coverage lives in WebhookContractTest.
  // The Task 3.10 baseline that treated it as a thin alias for feedUpdate
  // was retired in Task 3.13 — feedWebhookUpdate now collapses any
  // non-TelegramMethod return to null and additionally requires a Fiber
  // context (the 55s deadline race uses Amp futures).

  public function testFeedUpdateRoutesEventContextThroughUserContextMiddleware(): void
  {
    // The dispatcher wires UserContextMiddleware as the first outer
    // middleware, so handlers see `event_context`, `event_from_user`,
    // `event_chat`, `event_thread_id` keys populated by the time they run.
    $dispatcher = new Dispatcher();
    $observed = null;
    $dispatcher->message->register(static function (
      EventContext $event_context,
      ?User $event_from_user,
      ?Chat $event_chat,
    ) use (&$observed): string {
      $observed = [
        'ctx' => $event_context,
        'user' => $event_from_user,
        'chat' => $event_chat,
      ];

      return 'ok';
    });

    $bot = new MockedBot();
    $chat = new Chat(id: 100, type: 'private');
    $user = new User(id: 200, isBot: false, firstName: 'Tester');
    $message = new Message(messageId: 1, date: new DateTime('@0'), chat: $chat, fromUser: $user);
    $update = new Update(updateId: 1, message: $message);

    $dispatcher->feedUpdate($bot, $update);

    self::assertNotNull($observed);
    self::assertInstanceOf(EventContext::class, $observed['ctx']);
    self::assertSame($chat, $observed['chat']);
    self::assertSame($user, $observed['user']);
  }

  public function testHandlerExceptionRethrownWhenNoErrorObserverRegistered(): void
  {
    // ErrorsMiddleware catches the exception, asks the errors observer to
    // handle it, gets UNHANDLED back (no handlers registered), and re-raises
    // the original Throwable. Mirrors upstream behaviour.
    $dispatcher = new Dispatcher();
    $dispatcher->message->register(static function (): never {
      throw new RuntimeException('handler bomb');
    });

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('handler bomb');

    $dispatcher->feedUpdate(new MockedBot(), self::messageUpdate('hi'));
  }

  public function testHandlerExceptionRoutedToRegisteredErrorObserver(): void
  {
    // When an error observer is registered, it receives an ErrorEvent with
    // the offending Update and the original Throwable. If the observer
    // returns a value (truthy non-sentinel), that becomes the dispatcher's
    // return value instead of the exception propagating.
    $dispatcher = new Dispatcher();
    $dispatcher->message->register(static function (): never {
      throw new RuntimeException('handler bomb');
    });
    $observed = null;
    $dispatcher->errors->register(static function (ErrorEvent $event) use (&$observed): string {
      $observed = $event;

      return 'recovered';
    });

    $update = self::messageUpdate('hi');
    $result = $dispatcher->feedUpdate(new MockedBot(), $update);

    self::assertSame('recovered', $result);
    self::assertInstanceOf(ErrorEvent::class, $observed);
    self::assertSame($update, $observed->update);
    self::assertInstanceOf(RuntimeException::class, $observed->exception);
    self::assertSame('handler bomb', $observed->exception->getMessage());
  }

  public function testSilentCallRequestDispatchesViaBot(): void
  {
    // The webhook fall-through stub simply calls $bot($method). Task 3.13
    // adds the deadline-aware queue-and-skip semantics; for Task 3.10 the
    // contract is just "dispatch to bot and return the result".
    $bot = new MockedBot();
    $method = new GetMe();
    $user = new User(id: 42, isBot: true, firstName: 'Bot', username: 'tbot');
    $bot->addResultFor(GetMe::class, ok: true, result: $user);

    $dispatcher = new Dispatcher();
    $result = $dispatcher->silentCallRequest($bot, $method);

    self::assertSame($user, $result);
  }

  public function testInheritsObserverMapShapeFromRouter(): void
  {
    // Sanity: Dispatcher inherits the 25-update-type observer map plus the
    // error observer. We do NOT use a synthetic 'update' observer (upstream
    // does — `aiogram/dispatcher/dispatcher.py:72`); the port routes
    // directly to per-type observers because the Router is already
    // schema-derived.
    $dispatcher = new Dispatcher();

    self::assertSame(
      [...Router::UPDATE_TYPES, 'error'],
      array_keys($dispatcher->observers),
    );
  }

  public function testFeedUpdateReturnsUnhandledWhenNoMatchingHandler(): void
  {
    // A registered observer with no handlers returns UNHANDLED — the
    // dispatcher forwards that verbatim. Distinguishable from null /
    // false return values by identity (`===`).
    $dispatcher = new Dispatcher();

    $result = $dispatcher->feedUpdate(new MockedBot(), self::messageUpdate('hi'));

    self::assertSame(UnhandledSentinel::instance(), $result);
  }

  public function testFeedWebhookUpdateAcceptsRawArrayUpdate(): void
  {
    // Fix I3: feedWebhookUpdate's first positional `update` parameter accepts
    // either an `Update` instance or a wire-shaped associative array. The
    // array form is the canonical webhook adapter input (the HTTP body decoded
    // with json_decode($body, true)). When given an array, the dispatcher
    // hydrates it via `Serializer::load(Update::class, ...)` before running
    // the dispatch chain. Mirrors upstream's `feed_webhook_update` overload
    // at `dispatcher.py:436-444`.
    $dispatcher = new Dispatcher();
    $observed = null;
    $dispatcher->message->register(static function (Update $event_update) use (&$observed): SendMessage {
      $observed = $event_update;

      return new SendMessage(chatId: $event_update->message?->chat->id ?? 0, text: 'echo');
    });
    $rawUpdate = [
      'update_id' => 77,
      'message' => [
        'message_id' => 1,
        'date' => 0,
        'chat' => ['id' => 11, 'type' => 'private'],
        'text' => 'hi raw',
      ],
    ];

    $result = $this->runAsync(static fn() => $dispatcher->feedWebhookUpdate(new MockedBot(), $rawUpdate));

    self::assertInstanceOf(SendMessage::class, $result);
    self::assertInstanceOf(Update::class, $observed);
    self::assertSame(77, $observed->updateId);
    self::assertSame('hi raw', $observed->message?->text);
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /**
   * Build a minimal `Update` carrying a text Message with the given body.
   * Used as the canonical fixture across tests; keeping the constructor
   * args inline in every test would noise out the actual scenario being
   * verified.
   */
  private static function messageUpdate(string $text): Update
  {
    $chat = new Chat(id: 1, type: 'private');
    $user = new User(id: 2, isBot: false, firstName: 'Tester');
    $message = new Message(messageId: 1, date: new DateTime('@0'), chat: $chat, fromUser: $user, text: $text);

    return new Update(updateId: 1, message: $message);
  }

  private static function makeUser(): User
  {
    return new User(id: 2, isBot: false, firstName: 'Tester');
  }
}
