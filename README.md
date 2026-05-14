# phpbotgram

A modern PHP 8.5 port of the [aiogram](https://github.com/aiogram/aiogram) Telegram Bot framework.

`phpbotgram` keeps the layered architecture of the Python upstream — `Bot`,
`Session`, `Dispatcher`, `Router`, filters, middlewares, FSM — but trades
coroutines for [amphp](https://amphp.org/) v3 fibers and uses native PHP 8.5
features (readonly classes, asymmetric visibility, property hooks, attributes,
native enums) throughout.

## Requirements

- PHP 8.5+
- ext-sodium (Web App / Login Widget signature verification)
- HTTP transport — `amphp/http-client ^5` (required), used by the default
  `AmphpSession` adapter
- Composer 2.5+

## Install

```bash
composer require gruven/phpbotgram
```

## Quickstart — echo bot

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

$token = getenv('BOT_TOKEN') ?: throw new RuntimeException('BOT_TOKEN missing');
$bot = new Bot($token);
$dispatcher = new Dispatcher();

$dispatcher->message->register(static function (Message $event): void {
  $text = $event->text ?? '';
  if ($text === '') {
    return;
  }
  $event->answer($text)->emit();
});

$dispatcher->runPolling(new PollingOptions(), $bot);
```

Run with `BOT_TOKEN=… php echo_bot.php`. The full example (with extra
documentation) lives at `examples/echo_bot.php`.

## Examples

All examples are runnable and self-contained. Set `BOT_TOKEN` before
launching (and `BOT_TOKEN_2` for `examples/multibot.php`, which adds a
second bot to the dispatcher when present).

| Path | Demonstrates |
| --- | --- |
| `examples/echo_bot.php` | Long-polling echo bot |
| `examples/echo_bot_webhook.php` | amphp/http-server webhook via `SimpleRequestHandler` + `AmphpServer::run` |
| `examples/error_handling.php` | Global error observer that logs uncaught exceptions |
| `examples/finite_state_machine.php` | Inline FSM (no scenes) for a multi-step form |
| `examples/scene.php` | Scene-based FSM with `SceneRegistry::add([Scene::class])` |
| `examples/quiz_scene.php` | Branching scene flow with conditional transitions |
| `examples/own_filter.php` | Custom `Filter` subclass plumbed into `$dispatcher->message->register` |
| `examples/specify_updates.php` | Restricting `PollingOptions` to a subset of update types |
| `examples/context_addition_from_filter.php` | Filter returning kwargs consumed by the handler |
| `examples/multibot.php` | One `Dispatcher` driving several `Bot` instances |
| `examples/without_dispatcher.php` | Raw `getUpdates` loop bypassing the dispatcher |
| `examples/stars_invoice.php` | Telegram Stars `sendInvoice` + `PreCheckoutQuery` flow |

## Core concepts

### Bot and Session

`Bot` is a thin facade that builds typed API method DTOs (`SendMessage`,
`SendPhoto`, …) and dispatches them through the `BaseSession`. The default
session is `AmphpSession`, which builds an `amphp/http-client` instance via
`HttpClientBuilder`, encodes form bodies, and surfaces Telegram error
responses as typed exceptions (`TelegramRetryAfter`,
`TelegramServerException`, `TelegramBadRequestException`,
`TelegramConflictException`, `TelegramForbiddenException`,
`TelegramNetworkException`, …). Polling-loop backoff on `RetryAfter` lives
in the dispatcher, not the session itself.

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Client\TelegramApiServer;

$bot = new Bot(
  token: $token,
  session: new AmphpSession(api: TelegramApiServer::production()),
);
```

Every typed API method returns its result directly (e.g. `sendMessage(...)`
returns `Types\Message`). For deferred dispatch use the underlying method
DTO directly via `emit()`:

```php
use Gruven\PhpBotGram\Methods\SendMessage;

$send = new SendMessage(chatId: 1, text: 'hi');
$result = $bot($send);              // synchronous in the current fiber
$alsoResult = $send->emit($bot);    // equivalent — TelegramMethod::emit()
```

### Dispatcher and Router

`Dispatcher` is a `Router` that owns the polling loop. Routers cascade — a
parent router runs its own filters and middlewares before delegating to
included child routers (`$dispatcher->includeRouter($shopRouter)`).

```php
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswerMiddleware;

$router = new Router();
$router->message->register($handler, filters: [$filter1, $filter2]);
$router->callbackQuery->outerMiddleware(new CallbackAnswerMiddleware());
$dispatcher->includeRouter($router);
```

### Filters and the F-DSL

`Filter` instances are invokable; they may return `false`, `true`, or a
kwargs array merged into the handler arguments. Built-in filters cover
commands (`Command`), magic field tests via the `F` constant
(`use const Gruven\PhpBotGram\F;`), `CallbackData::filter()`, state
predicates (`StateFilter`; a bare `State` instance is also directly
usable as a filter), and combinators
(`Filter::all()`, `Filter::any()`, `Filter::invertOf()`).

```php
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\Filter;
use const Gruven\PhpBotGram\F;

$router->message->register(
  $handler,
  filters: [
    Filter::all(
      new Command('buy'),
      F->chat->type->equals('private')->asFilter(),
    ),
  ],
);
```

### FSM scenes

Scenes are explicit (no metaclass auto-discovery): subclass `Scene`, declare
state methods with `#[OnMessage]` / `#[OnCallbackQuery]`, and register
through `SceneRegistry`. Scene history, state, and storage isolation are
all driven by `FsmContext`.

```php
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistry;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Types\Message;

$registry = new SceneRegistry($dispatcher);
$registry->add([QuizScene::class]);

$dispatcher->message->register(
  static function (Message $event, ScenesManager $scenes): void {
    $scenes->enter(QuizScene::class);
  },
  filters: [new Command('quiz')],
);
```

The framework injects `ScenesManager $scenes` as a handler kwarg when
`SceneRegistry` has been attached to the dispatcher.

### Webhook

`Webhook\Server\AmphpServer::run()` boots an `amphp/http-server` listener
that routes inbound `Update` payloads through `SimpleRequestHandler`
(single bot) or `TokenBasedRequestHandler` (multi-tenant). For full
control over an existing `amphp/http-server` instance, use
`Webhook\Setup::register()` to splice the bot lifecycle into your own
startup/shutdown hooks.

```php
use Gruven\PhpBotGram\Webhook\Server\AmphpServer;
use Gruven\PhpBotGram\Webhook\SimpleRequestHandler;

$handler = new SimpleRequestHandler(dispatcher: $dispatcher, bot: $bot);
AmphpServer::run(
  handler: $handler,
  dispatcher: $dispatcher,
  host: '0.0.0.0',
  port: 8080,
  path: '/webhook',
);
```

## Testing

```bash
composer install
vendor/bin/phpunit                 # full suite
vendor/bin/phpunit --testsuite phpbotgram
vendor/bin/phpstan analyse         # level 9
vendor/bin/php-cs-fixer fix --dry-run --diff
make coverage-gate                 # XDEBUG_MODE=coverage + per-module floors
```

Tests against live external services are env-gated to keep CI offline by
default:

| Variable | What it unlocks |
| --- | --- |
| `PHPBOTGRAM_REDIS_DSN` | `RedisStorage` integration cases |
| `PHPBOTGRAM_MONGO_DSN` | `MongoStorage` integration cases |

## Project structure

```
src/
  Bot.php             Bot facade (codegen — regenerate via `make regenerate`)
  Client/             Bot defaults, Session, Serializer, HTTP transport
  Dispatcher/         Router cascade, observers, middleware chain
  Filters/            Built-in filters + F-DSL
  Fsm/                Storage backends, Strategy, Scenes
  Methods/            Typed API method DTOs (codegen output)
  Types/              Telegram type DTOs (codegen output)
  Utils/              TextDecoration, Keyboard builders, WebApp, …
  Webhook/            amphp/http-server runner + IP filter
tools/generator/      Phase 2 codegen producing Methods/ and Types/
tests/                Mirrors src/ — unit + integration cases
examples/             Runnable bots (see table above)
docs/superpowers/     Specs + implementation plan
```

## License

MIT. See `LICENSE`.

## Upstream

Tracks aiogram 3.28. Behaviour divergences from upstream are documented
inline at the call site (search for `# Divergence:` in the source) and in
`docs/superpowers/specs/`.
