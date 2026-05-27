<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\Middlewares\EventContext;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end integration test guarding the dispatcher kwargs threading
 * through `Filter::__invoke`. Exercises the full pipeline:
 *
 *   `feedUpdate` → `UserContextMiddleware` → `TelegramEventObserver::triggerCore`
 *   → `FilterObject::call` → `CallableObject::prepareKwargs` → filter `__invoke`.
 *
 * Before the variadic fix (`mixed ...$kwargs`), `prepareKwargs` would intersect
 * the dispatcher bag against the non-variadic parameter names (`event`, `kwargs`)
 * and silently drop every other key (`bot`, `event_context`, `state`, etc.).
 * The filter received an empty `$kwargs = []` regardless of what the dispatcher
 * injected. This test guards against regressions of that bug.
 *
 * Filters are registered by calling the Filter instance as a Closure
 * (`$filter(...)`) — `TelegramEventObserver::filter()` and `register()` accept
 * only `Closure` objects, and PHP's first-class callable syntax `$filter(...)`
 * wraps the `Filter::__invoke` method as a `Closure` that `CallableObject`
 * then reflects over.
 *
 * @internal
 *
 * @coversNothing
 */
final class FilterDispatcherIntegrationTest extends TestCase
{
  protected function tearDown(): void
  {
    // Clear the FiberLocal `current bot` slot so a failed feedUpdate does not
    // leak the binding into the next test.
    Bot::setCurrent(null);
  }

  public function testFilterReceivesFullDispatcherKwargsBagIncludingBot(): void
  {
    // Reviewer's reproducer — adapted for feedUpdate so it exercises the
    // real dispatcher pipeline rather than a unit-level FilterObject call.
    //
    // The custom Filter captures its kwargs bag into `$capturedKwargs` and
    // unconditionally accepts (returns `true`). After feedUpdate we assert
    // that `bot` and `event_context` are present, proving the variadic tail
    // `mixed ...$kwargs` correctly short-circuits `prepareKwargs` into
    // pass-through mode.
    /** @var null|array<string, mixed> $capturedKwargs */
    $capturedKwargs = null;

    $filter = new class extends Filter {
      /** @var null|array<string, mixed> */
      public ?array $bag = null;

      public function __invoke(object $event, mixed ...$kwargs): array|bool
      {
        // `$kwargs` is the variadic capture — a `array<string, mixed>` at runtime.
        /** @var array<string, mixed> $kwargs */
        $this->bag = $kwargs;

        return true;
      }
    };

    $dispatcher = new Dispatcher();
    // Register via first-class callable syntax: $filter(...) produces a Closure
    // wrapping `$filter->__invoke(...)` so CallableObject can reflect over it.
    $dispatcher->message->filter($filter(...));
    $dispatcher->message->register(static fn(): string => 'ok');

    $bot = new MockedBot();
    $update = self::messageUpdate('hello')->withBot($bot);

    $dispatcher->feedUpdate($bot, $update);

    $capturedKwargs = $filter->bag;

    // The filter must have been invoked.
    self::assertNotNull($capturedKwargs, 'Filter must have been invoked by the dispatcher pipeline.');

    // The `bot` kwarg is injected by the dispatcher before triggering observers.
    self::assertArrayHasKey('bot', $capturedKwargs, '"bot" must appear in the filter kwargs bag.');
    self::assertSame($bot, $capturedKwargs['bot'], 'The bot in kwargs must be the exact dispatching bot instance.');

    // The `event_context` kwarg is injected by UserContextMiddleware.
    self::assertArrayHasKey('event_context', $capturedKwargs, '"event_context" must appear in the filter kwargs bag.');
    self::assertInstanceOf(EventContext::class, $capturedKwargs['event_context']);

    // Note: `event` is consumed by the named `$event` positional parameter in
    // `__invoke(object $event, mixed ...$kwargs)` — the dispatcher passes it as
    // `event: $event` (named arg), which PHP routes to the first positional
    // parameter rather than into the variadic `$kwargs`. This is correct: the
    // $event param is the primary event object, and `$kwargs` holds the rest of
    // the bag. The `event_update` kwarg (the full Update object) IS in the bag.
    self::assertArrayHasKey('event_update', $capturedKwargs, '"event_update" must appear in the filter kwargs bag.');
  }

  public function testFilterKwargsReturnMergesIntoHandlerKwargs(): void
  {
    // Guards the kwargs-merge pipeline end-to-end: a filter that returns
    // an array contributes its entries to the handler's invocation kwargs.
    $handlerSawExtra = null;

    $filter = new class extends Filter {
      public function __invoke(object $event, mixed ...$kwargs): array
      {
        return ['injected_by_filter' => 'hello_from_filter'];
      }
    };

    $dispatcher = new Dispatcher();
    $dispatcher->message->filter($filter(...));
    $dispatcher->message->register(static function (string $injected_by_filter) use (&$handlerSawExtra): string {
      $handlerSawExtra = $injected_by_filter;

      return 'ok';
    });

    $bot = new MockedBot();
    $dispatcher->feedUpdate($bot, self::messageUpdate('hi')->withBot($bot));

    self::assertSame('hello_from_filter', $handlerSawExtra, 'Handler must receive kwargs injected by a filter return.');
  }

  public function testCommandFilterSeesRealBotViaNonEmptyKwargsBag(): void
  {
    // Specific regression guard for `Command::__invoke` which reads
    // `$kwargs['bot'] ?? null` to perform mention validation. Before the
    // variadic fix, `$bot` was always `null` even when a real Bot was
    // dispatching. After the fix, the bot flows through.
    //
    // We use a spy filter to capture the bag and assert `bot` is present.
    $capturedBot = 'not-set';

    $botCapture = new class extends Filter {
      /** @var mixed */
      public mixed $capturedBot = 'not-set';

      public function __invoke(object $event, mixed ...$kwargs): array|bool
      {
        $this->capturedBot = array_key_exists('bot', $kwargs) ? $kwargs['bot'] : 'KEY_MISSING';

        return true;
      }
    };

    $dispatcher = new Dispatcher();
    $dispatcher->message->filter($botCapture(...));
    $dispatcher->message->register(static fn(): string => 'ok');

    $bot = new MockedBot();
    $chat = new Chat(id: 1, type: 'private', bot: $bot);
    $user = new User(id: 2, isBot: false, firstName: 'Tester', bot: $bot);
    $message = new Message(
      messageId: 1,
      date: new DateTime('@0'),
      chat: $chat,
      fromUser: $user,
      text: '/start',
      bot: $bot,
    );
    $update = new Update(updateId: 1, message: $message, bot: $bot);

    $dispatcher->feedUpdate($bot, $update);
    $capturedBot = $botCapture->capturedBot;

    self::assertNotSame('KEY_MISSING', $capturedBot, '"bot" key must exist in the kwargs bag — not missing.');
    self::assertNotSame('not-set', $capturedBot, 'Filter must have been invoked.');
    self::assertInstanceOf(Bot::class, $capturedBot, 'The "bot" kwarg must be a real Bot instance.');
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private static function messageUpdate(string $text): Update
  {
    $chat = new Chat(id: 1, type: 'private');
    $user = new User(id: 2, isBot: false, firstName: 'Tester');
    $message = new Message(messageId: 1, date: new DateTime('@0'), chat: $chat, fromUser: $user, text: $text);

    return new Update(updateId: 1, message: $message);
  }
}
