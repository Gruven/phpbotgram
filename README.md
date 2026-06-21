# phpbotgram

[![PHP version](https://img.shields.io/badge/php-%5E8.5-777bb4.svg?logo=php)](https://www.php.net/releases/8.5/) [![PHPStan level](https://img.shields.io/badge/PHPStan-level%209-2563eb.svg)](https://phpstan.org/) [![Tests](https://img.shields.io/badge/tests-2190%20passing-3fb950.svg)](#testing) [![Coverage gate](https://img.shields.io/badge/coverage--gate-passing-3fb950.svg)](#testing) [![License](https://img.shields.io/badge/license-MIT-yellow.svg)](LICENSE) [![Upstream](https://img.shields.io/badge/aiogram-3.29.0-blueviolet.svg?logo=python)](https://github.com/aiogram/aiogram) [![Bot API](https://img.shields.io/badge/Bot%20API-10.1-26a5e4.svg?logo=telegram)](https://core.telegram.org/bots/api)

A modern PHP 8.5 port of the [aiogram](https://github.com/aiogram/aiogram) Telegram Bot framework.

`phpbotgram` keeps the layered architecture of the Python upstream — `Bot`, `Session`, `Dispatcher`, `Router`, filters, middlewares, FSM — but trades coroutines for [amphp](https://amphp.org/) v3 fibers and uses native PHP 8.5 features (readonly classes, asymmetric visibility, property hooks, attributes, native enums) throughout.

## Links

- **API documentation** — published at <https://gruven.github.io/phpbotgram/> (GitHub Pages, rebuilt on every push to `master`). Regenerate locally with `composer docs-api` (or `make docs-api`); output lands in `build/docs/api/index.html`.
- **Narrative documentation:** <https://gruven.github.io/phpbotgram/en/dev/guide/> (tutorial, cookbook, concepts).
- **Design spec** — [`docs/superpowers/specs/`](docs/superpowers/specs/).
- **Implementation plan** — [`docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md`](docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md).
- **Changelog** — [`CHANGELOG.md`](CHANGELOG.md).
- **Deployment templates** — [`deploy/`](deploy/) (nginx, systemd, Docker compose).
- **Runnable examples** — [`examples/`](examples/) (see table below).

## Requirements

- PHP 8.5+
- ext-sodium (Web App / Login Widget signature verification)
- HTTP transport — `amphp/http-client ^5` (required), used by the default `AmphpSession` adapter
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

Run with `BOT_TOKEN=… php echo_bot.php`. The full example (with extra documentation) lives at `examples/echo_bot.php`.

## Examples

All examples are runnable and self-contained. Set `BOT_TOKEN` before launching (and `BOT_TOKEN_2` for `examples/multibot.php`, which adds a second bot to the dispatcher when present).

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
| `examples/inline_keyboard.php` | Inline keyboard builder + typed `CallbackData` + callback auto-answer |
| `examples/deep_linking.php` | Deep-link `/start` payload generation and handling |
| `examples/file_download.php` | Downloading user-sent documents/photos via `Bot::download()` |

## Core concepts

### Bot and Session

`Bot` is a thin facade that builds typed API method DTOs (`SendMessage`, `SendPhoto`, …) and dispatches them through the `BaseSession`. The default session is `AmphpSession`, which builds an `amphp/http-client` instance via `HttpClientBuilder`, encodes form bodies, and surfaces Telegram error responses as typed exceptions (`TelegramRetryAfter`, `TelegramServerException`, `TelegramBadRequestException`, `TelegramConflictException`, `TelegramForbiddenException`, `TelegramNetworkException`, …). Polling-loop backoff on `RetryAfter` lives in the dispatcher, not the session itself.

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Client\TelegramApiServer;

$bot = new Bot(
  token: $token,
  session: new AmphpSession(api: TelegramApiServer::production()),
);
```

Every typed API method returns its result directly (e.g. `sendMessage(...)` returns `Types\Message`). For deferred dispatch use the underlying method DTO directly via `emit()`:

```php
use Gruven\PhpBotGram\Methods\SendMessage;

$send = new SendMessage(chatId: 1, text: 'hi');
$result = $bot($send);              // synchronous in the current fiber
$alsoResult = $send->emit($bot);    // equivalent — TelegramMethod::emit()
```

### Dispatcher and Router

`Dispatcher` is a `Router` that owns the polling loop. Routers cascade — a parent router runs its own filters and middlewares before delegating to included child routers (`$dispatcher->includeRouter($shopRouter)`).

```php
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswerMiddleware;

$router = new Router();
$router->message->register($handler, filters: [$filter1, $filter2]);
$router->callbackQuery->outerMiddleware(new CallbackAnswerMiddleware());
$dispatcher->includeRouter($router);
```

### Filters and the F-DSL

`Filter` instances are invokable; they may return `false`, `true`, or a kwargs array merged into the handler arguments. Built-in filters cover commands (`Command`), magic field tests via the `F` constant (`use const Gruven\PhpBotGram\F;`), `CallbackData::filter()`, state predicates (`StateFilter`; a bare `State` instance is also directly usable as a filter), and combinators (`Filter::all()`, `Filter::any()`, `Filter::invertOf()`).

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

Scenes are explicit (no metaclass auto-discovery): subclass `Scene`, declare state methods with `#[OnMessage]` / `#[OnCallbackQuery]`, and register through `SceneRegistry`. Scene history, state, and storage isolation are all driven by `FsmContext`.

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

The framework injects `ScenesManager $scenes` as a handler kwarg when `SceneRegistry` has been attached to the dispatcher. While a scene state is active, router subtrees that contain that scene state are tried before broad parent catch-all handlers so free-text scene replies are not consumed by root fallbacks.

### Webhook

`Webhook\Server\AmphpServer::run()` boots an `amphp/http-server` listener that routes inbound `Update` payloads through `SimpleRequestHandler` (single bot) or `TokenBasedRequestHandler` (multi-tenant). For full control over an existing `amphp/http-server` instance, use `Webhook\Setup::register()` to splice the bot lifecycle into your own startup/shutdown hooks.

`amphp/http-server` is a suggested dependency — install it explicitly before using webhook mode:

```bash
composer require amphp/http-server
```

```php
use Gruven\PhpBotGram\Webhook\Server\AmphpServer;
use Gruven\PhpBotGram\Webhook\SimpleRequestHandler;

$handler = new SimpleRequestHandler(dispatcher: $dispatcher, bot: $bot);
AmphpServer::run(
  handler: $handler,
  dispatcher: $dispatcher,
  // Bind to 127.0.0.1 when sitting behind a reverse proxy (recommended).
  // Use 0.0.0.0 only if exposing the bot directly to the internet — see
  // deploy/nginx/phpbotgram-webhook.conf for the proxy template.
  host: '127.0.0.1',
  port: 8080,
  path: '/webhook',
);
```

## Testing

```bash
composer install
composer test                      # full suite (or vendor/bin/phpunit)
composer stan                      # PHPStan level 9
composer lint                      # php-cs-fixer dry run
composer coverage-gate             # XDEBUG_MODE=coverage + per-module floors
composer docs-api                  # generate API docs into build/docs/api/
```

Equivalent `make test / make stan / make lint / make coverage-gate / make docs-api` targets exist for contributors who prefer the Makefile interface.

Tests against live external services are env-gated to keep CI offline by default:

| Variable | What it unlocks |
| --- | --- |
| `PHPBOTGRAM_TEST_REDIS_DSN` | `RedisStorage` integration cases |
| `PHPBOTGRAM_TEST_MONGO_DSN` | `MongoStorage` integration cases |

## Project structure

```
src/
  Bot.php             Bot facade (codegen — regenerate via `make regenerate`)
  Client/             Bot defaults, Session, Serializer, HTTP transport
  Dispatcher/         Router cascade, observers, middleware chain
  Enums/              Telegram-side enums (ParseMode, ChatType, …)
  Exceptions/         TelegramApiException hierarchy + transport exceptions
  F.php               `const F` MagicFilter root for the F-DSL
  Filters/            Built-in filters + F-DSL field wrappers
  Fsm/                Storage backends, Strategy, Scenes
  Methods/            Typed API method DTOs (codegen output)
  Types/              Telegram type DTOs (codegen output)
  Utils/              TextDecoration, Keyboard builders, WebApp, …
  Webhook/            amphp/http-server runner + IP filter
tools/generator/      Phase 2 codegen producing Methods/, Types/, Enums/
scripts/              coverage-gate.php
tests/                Mirrors src/ — unit + integration cases
examples/             Runnable bots (see table above)
deploy/               nginx / systemd / Docker compose templates
docs/superpowers/     Specs + implementation plan
```

## License

MIT. See `LICENSE`.

## Upstream

Tracks aiogram 3.29.0. Behaviour divergences from upstream are documented inline at the call site (search for `# Divergence:` in the source) and in `docs/superpowers/specs/`.
