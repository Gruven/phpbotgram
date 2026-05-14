# phpbotgram

A modern PHP 8.5 port of the [aiogram](https://github.com/aiogram/aiogram) Telegram Bot framework.

`phpbotgram` keeps the layered architecture of the Python upstream — `Bot`, `Session`,
`Dispatcher`, `Router`, filters, middlewares, FSM — but trades coroutines for
[amphp](https://amphp.org/) v3 fibers and uses native PHP 8.5 features
(readonly classes, asymmetric visibility, property hooks, attributes, native enums)
throughout.

## Requirements

- PHP 8.5+
- ext-sodium (Web App / Login Widget signature verification)
- A PSR-18 HTTP client discoverable through `php-http/discovery`
  (the package suggests `amphp/http-client` for production)
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

use Gruven\PhpBotGram\Bot\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Types\Message;
use function Amp\async;
use function Amp\Future\await;

$bot = new Bot(token: getenv('BOT_TOKEN') ?: throw new RuntimeException('BOT_TOKEN missing'));
$dispatcher = new Dispatcher();

$dispatcher->message->register(
  function (Message $message) use ($bot): void {
    $bot->sendMessage(chatId: $message->chat->id, text: $message->text ?? '')->emit();
  },
  new Command('start'),
);

await([async(fn () => $dispatcher->startPolling($bot))]);
```

Run with `BOT_TOKEN=… php echo_bot.php`. The full example lives at
`examples/echo_bot.php`.

## Examples

All examples are runnable and self-contained. Set `BOT_TOKEN` (and, where
required, `WEBHOOK_URL`, `BASE_BOT_TOKENS`, etc.) before launching.

| Path | Demonstrates |
| --- | --- |
| `examples/echo_bot.php` | Long-polling echo bot with `/start` and message handlers |
| `examples/echo_bot_webhook.php` | amphp/http-server webhook setup via `SimpleRequestHandler` |
| `examples/error_handling.php` | Global error observer that logs uncaught exceptions |
| `examples/finite_state_machine.php` | Inline FSM (no scenes) for a multi-step form |
| `examples/scene.php` | Scene-based FSM with `SceneRegistry::add()` and `enter()` |
| `examples/quiz_scene.php` | Branching scene flow with conditional transitions |
| `examples/own_filter.php` | Custom `Filter` subclass plumbed into `Router::message` |
| `examples/specify_updates.php` | Restricting `startPolling` to a subset of update types |
| `examples/context_addition_from_filter.php` | Filter returning kwargs consumed by the handler |
| `examples/multibot.php` | One `Dispatcher` driving several `Bot` instances |
| `examples/without_dispatcher.php` | Raw `getUpdates` loop bypassing the dispatcher |
| `examples/stars_invoice.php` | Telegram Stars `sendInvoice` + `PreCheckoutQuery` flow |

## Core concepts

### Bot and Session

`Bot` is a thin shell that builds typed API method DTOs (`SendMessage`,
`SendPhoto`, …) and dispatches them through the `Session`. `Session` owns the
HTTP transport (`Http\Transport` discovered via `php-http/discovery`),
multipart serialisation for file uploads, retry/backoff on `RetryAfter` and
network errors, and graceful shutdown.

```php
$bot = new Bot(
  token: $token,
  session: new Session(timeout: 30.0, baseUrl: 'https://api.telegram.org'),
);
```

Each method returns a `Request<TResult>` that emits via `->emit()` (synchronous
in the calling fiber) or `->future()` (returns an `Amp\Future<TResult>` for
concurrent dispatch).

### Dispatcher and Router

`Dispatcher` is a `Router` that owns the polling loop. Routers cascade — a
parent router invokes its own filters and middlewares before delegating to
included child routers (`$dispatcher->include($shopRouter)`).

```php
$router = new Router();
$router->message->register($handler, $filter1, $filter2);
$router->callbackQuery->middleware(new CallbackAnswerMiddleware());
$dispatcher->include($router);
```

### Filters and the F-DSL

`Filter` instances are invokable; they may return `false`, `true`, or a kwargs
array merged into the handler arguments. Built-in filters cover commands
(`Command`), magic field tests (`MagicFilter` / `F`), `CallbackData::filter()`,
state predicates (`StateFilter`, `State::filter()`), and combinators
(`Filter::all()`, `Filter::any()`, `Filter::invert()`).

```php
$router->message->register(
  $handler,
  Filter::all(new Command('buy'), F::chat()->type()->equals('private')),
);
```

### FSM scenes

Scenes are explicit (no metaclass auto-discovery): subclass `Scene`, declare
state methods with `#[OnEvent]`, and register through `SceneRegistry`.
Scene history, state, and storage isolation are all driven by `FSMContext`.

```php
$scenes = new SceneRegistry($dispatcher);
$scenes->add(QuizScene::class);
$dispatcher->message->register(
  fn (Message $m, SceneWizard $scene) => $scene->enter(QuizScene::class),
  new Command('quiz'),
);
```

### Webhook

`Webhook\Server\AmphpServer` runs an `amphp/http-server` listener that routes
inbound `Update` payloads through `SimpleRequestHandler` (single bot) or
`TokenBasedRequestHandler` (multi-tenant). `Setup::install()` registers the
webhook with Telegram and bootstraps the server in one call.

```php
$setup = new Setup(
  bot: $bot,
  dispatcher: $dispatcher,
  url: 'https://example.com/webhook',
  secretToken: $secret,
);
$setup->install();
```

## Testing

```bash
composer install
vendor/bin/phpunit                 # full suite
vendor/bin/phpunit --testsuite unit
vendor/bin/phpstan analyse         # level 9
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Tests against live external services are env-gated to keep CI offline by
default:

| Variable | What it unlocks |
| --- | --- |
| `PHPBOTGRAM_TEST_REDIS_DSN` | `RedisStorage` integration cases |
| `PHPBOTGRAM_TEST_MONGO_DSN` | `MongoStorage` integration cases |
| `PHPBOTGRAM_TEST_TELEGRAM_TOKEN` | End-to-end smoke against api.telegram.org |

## Project structure

```
src/
  Bot/                Bot + Session + HTTP transport
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

Tracks aiogram 3.28. Behaviour diverges from upstream are documented inline at
the call site (search for `# Divergence:` in the source) and in
`docs/superpowers/specs/`.
