# phpbotgram — PHP port of aiogram (design)

> Date: 2026-05-11
> Status: draft, pending implementation plan
> Source framework: [aiogram](https://github.com/aiogram/aiogram) 3.28.2 (Telegram Bot API 10.0)
> Target package: `gruven/phpbotgram` (`Gruven\PhpBotGram\` namespace), PHP ^8.5

## Goals

1. Provide an idiomatic PHP framework for the Telegram Bot API with the same public surface and mental model as aiogram 3.x — Router-based dispatcher, Pydantic-like DTOs, FSM with Scenes, async client, webhook and long polling.
2. Mirror aiogram's module layout 1-to-1 so any user familiar with aiogram can navigate phpbotgram by intuition. Deviate only where PHP best practices clearly demand (readonly classes, attributes vs decorators, Future vs awaitable).
3. Stay current with the upstream Telegram Bot API schema by reusing aiogram's `.butcher/schema/schema.json` plus its alias/replace/default patches as the codegen source of truth.
4. Modern PHP: target PHP 8.5, leverage readonly classes, asymmetric visibility, property hooks, backed enums, intersection/union types, first-class callable syntax, and the pipe operator.
5. Production-ready async runtime built on Fibers via amphp v3 / Revolt, with optional sync usage paths.
6. Full test suite ported from aiogram to PHPUnit ^13.1 with ≥90 % core coverage and parity with upstream behavior.

## Non-goals

* Backward compatibility with any prior PHP Telegram libraries.
* Synchronous-only API surface as the primary entry point. If a PSR-18 sync session is needed for FPM-style deployments we will ship it later as a separate optional package (`gruven/phpbotgram-psr18-session`).
* Drop-in compatibility with Symfony/Laravel-specific subsystems (HttpKernel, Eloquent storage). These can be added later as separate plug-in packages.
* Generating PHP code that is identical line-by-line to aiogram's Python output — only the abstractions and public API surface mirror.

## Architectural translation strategy

`A. 1-to-1 structural mirror.` aiogram modules map directly onto PHP namespaces. `aiogram/types/message.py` becomes `Gruven\PhpBotGram\Types\Message`, `aiogram/dispatcher/router.py` becomes `Gruven\PhpBotGram\Dispatcher\Router`, and so on. Class names, method names, lifecycle hooks, observer event names, filter behavior, FSM key layout and middleware contract are preserved. PHP idioms are applied inside implementation details (readonly classes, attributes, Future return types).

Alternatives considered and rejected:

* `B. PHP-native rewrite.` Restructure for PHP-native components (Symfony EventDispatcher, Doctrine cache). Better PHP ergonomics but loses cross-language navigability and the aiogram mental model the user explicitly asked to preserve.
* `C. Hybrid.` Mirror public surface, replace internals with Symfony components. Drags heavy transitive deps and partially defeats the point of a thin framework.

Choice: `A`.

## Technology stack

| Concern | Choice | Notes |
|---|---|---|
| PHP version | `^8.5` (64-bit) | Readonly classes/properties, asymmetric visibility, property hooks (8.4), `|>` pipe operator (8.5), backed enums, intersection/union types |
| HTTP client | `amphp/http-client` ^5 | Sole outbound transport: Fiber-aware HTTP/1.1+2, native streaming for `multipart/form-data` (chosen value v5.3.4) |
| Async runtime | `amphp/amp` ^3 + `revolt/event-loop` ^1 | `Future` API, signals, sync primitives (`Semaphore`, `LocalKeyedMutex`) |
| Byte streaming | `amphp/byte-stream` ^2 | Pulled by `amphp/http-client`; used directly for `InputFile` streaming |
| JSON | Native `json_encode/json_decode` with `JSON_THROW_ON_ERROR` | No external dep |
| Codegen | PHP CLI in `tools/generator/` + `twig/twig` (dev-only) | Generator reads `.butcher/schema/schema.json` + alias/replace/default patches; pure CLI, no runtime dependency |
| FSM storages (core) | Memory; Redis via `amphp/redis` ^2; Mongo via `mongodb/mongodb` ^2 | All three live in the core package per upstream parity; the driver packages are listed in `require-dev` and surfaced through `suggest` so library users only pull the ones they actually use |
| Webhook | `amphp/http-server` ^3 native adapter | Default and only built-in adapter is amphp-native; a PSR-7/PSR-15 bridge is intentionally deferred to a separate optional package (`gruven/phpbotgram-psr-webhook`, future) |
| Tests | `phpunit/phpunit` ^13.1 + in-house Fiber helper | `amphp/phpunit-util` is pinned to PHPUnit 9 and is incompatible with our PHPUnit 13 baseline; we ship a tiny `RunAsync` test helper (≈40 LOC) that drives Revolt's event loop inside test methods and cleans up pending callbacks in `tearDown` |
| Static analysis | `phpstan/phpstan` ^2.1 level 9 with generics via docblocks | `TelegramMethod<TReturn>` carried in `@template`/`@extends` (see "PHPStan generics layout" below) |
| Style | `friendsofphp/php-cs-fixer` (already configured) | Existing `.php-cs-fixer.dist.php` retained |
| PHP extensions (required) | `ext-mbstring`, `ext-json` | `ext-mbstring` for the `mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')` surrogate-pair accounting in `TextDecoration`; `ext-json` for the serializer |
| PHP extensions (suggested) | `ext-pcntl`, `ext-mongodb` ^2.3, `ext-sockets`, `ext-openssl`, `ext-fileinfo` | `ext-pcntl` enables `EventLoop::onSignal(SIGINT/SIGTERM)` for graceful polling shutdown (unix only); `ext-mongodb` is required by `mongodb/mongodb`; `ext-sockets`/`ext-openssl` are pulled transitively by `amphp/socket`; `ext-fileinfo` is used by `InputFile` mime detection. All listed in `composer.json#suggest` |
| PSR layer | None in public surface | `amphp/http-client` pulls `psr/http-message` transitively but phpbotgram code does not reference it. Webhook signatures are amphp-native (`Amp\Http\Server\Request`/`Response`). A PSR-7/15 bridge is deferred to `gruven/phpbotgram-psr-webhook` (future) |

## Namespace layout

```
Gruven\PhpBotGram\
├── Bot                              # client/bot.py — facade with all 178 API methods
├── Client\
│   ├── Session\BaseSession          # client/session/base.py — abstract
│   ├── Session\AmphpSession         # client/session/aiohttp.py — production async
│   ├── Session\Middleware\RequestMiddlewareManager
│   ├── Session\Middleware\BaseRequestMiddleware
│   ├── TelegramApiServer            # client/telegram.py (PRODUCTION, TEST, from_base)
│   ├── DefaultBotProperties         # client/default.py
│   ├── Default                      # client/default.py — Default sentinel
│   └── BotContextController         # client/context_controller.py
├── Types\*                          # 341 readonly DTO (codegen)
├── Methods\*                        # 178 readonly method classes (codegen)
├── Enums\*                          # 35 backed enums (codegen)
├── Dispatcher\
│   ├── Dispatcher                   # root Router with polling/webhook entry points
│   ├── Router
│   ├── Event\TelegramEventObserver
│   ├── Event\EventObserver          # for startup/shutdown
│   ├── Event\HandlerObject, FilterObject, CallableObject
│   ├── Event\Bases                  # UNHANDLED, REJECTED, SkipHandler exception
│   ├── Middlewares\BaseMiddleware
│   ├── Middlewares\ErrorsMiddleware
│   ├── Middlewares\UserContextMiddleware
│   └── Flags                        # FlagGenerator, extract_flags_from_object
├── Filters\
│   ├── Filter                       # abstract base
│   ├── Command, CommandStart, CommandObject
│   ├── CallbackData                 # base class for callback_data DTO
│   ├── StateFilter
│   ├── ChatMemberUpdatedFilter
│   ├── ExceptionTypeFilter
│   ├── MagicData                    # filters/magic_data.py — resolves a MagicFilter against middleware data
│   ├── Logic\AndFilter, OrFilter, InvertFilter
│   └── F\*                          # generated typed builders (see § F-DSL)
├── Fsm\
│   ├── State, StatesGroup, DefaultState
│   ├── Context                      # FSMContext
│   ├── FsmStrategy                  # enum
│   ├── Middleware\FsmContextMiddleware
│   ├── Scene\Scene, SceneRegistry, HistoryManager, SceneAction, After
│   └── Storage\BaseStorage, MemoryStorage, RedisStorage, MongoStorage,
│                DefaultKeyBuilder, StorageKey, BaseEventIsolation,
│                SimpleEventIsolation, DisabledEventIsolation, RedisEventIsolation
├── Webhook\
│   ├── RequestHandler\BaseRequestHandler
│   ├── RequestHandler\SimpleRequestHandler
│   ├── RequestHandler\TokenBasedRequestHandler
│   ├── Security\IpFilter
│   └── Server\AmphpServer
├── Utils\
│   ├── TextDecoration\TextDecoration, HtmlDecoration, MarkdownDecoration
│   ├── DeepLinking, Keyboard, MediaGroup, ChatAction, CallbackAnswer
│   ├── Backoff, BackoffConfig
│   ├── Payload, Token, Link, WebApp\WebAppSignature, AuthWidget
│   ├── I18n                         # shipped as a separate optional package `gruven/phpbotgram-i18n`; kept here for symmetry with upstream's `aiogram/utils/i18n/`
│   └── MagicFilter\MagicFilter      # public DSL: full port of `aiogram/utils/magic_filter.py` (which subclasses pip's `magic_filter`); used by `Command(magic=…)`, `Filters\MagicData`, scenes, ad-hoc filter expressions, plus as runtime substrate of the generated `Filters\F\*` builders
└── Exceptions\
    ├── PhpBotGramException          # AiogramError
    ├── DetailedException
    ├── TelegramApiException
    ├── TelegramNetworkException
    ├── TelegramBadRequest, TelegramConflictError, TelegramForbiddenError,
    │   TelegramMigrateToChat, TelegramNotFound, TelegramRetryAfter,
    │   TelegramServerError, TelegramUnauthorizedError, TelegramEntityTooLarge,
    │   RestartingTelegram, ClientDecodeError, DataNotDictLikeError
    ├── CallbackAnswerException, SceneException, UnsupportedKeywordArgument
    └── UpdateTypeLookupError
```

## Async runtime and HTTP layer

The framework is async-first using amphp v3 / Revolt. All session methods are Fiber-aware: their declared return types are plain values (e.g. `Message`), but they may suspend the current Fiber. Callers wanting concurrency wrap calls in `Amp\async(...)`. Handlers may return plain values or `Amp\Future` — the Dispatcher awaits both transparently.

`Gruven\PhpBotGram\Client\Session\BaseSession`:

* `abstract public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed;`
* `abstract public function close(): void;`
* `abstract public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream;`
* `public function prepareValue(mixed $value, Bot $bot, array &$files, bool $dumpsJson = true): mixed;` — port of `BaseSession.prepare_value` covering `Default` sentinel, `InputFile`, `DateTimeInterface`, enums, lists, dicts, and nested `TelegramObject`.
* `public function checkResponse(Bot $bot, TelegramMethod $method, int $statusCode, string $content): Response;` — port of `BaseSession.check_response` mapping HTTP status + Telegram error codes to typed exceptions.
* `public RequestMiddlewareManager $middleware { get; }` — chained around `makeRequest`.

`AmphpSession`:

* Built on `Amp\Http\Client\HttpClientBuilder`.
* Implements `multipart/form-data` body via `Amp\ByteStream\ReadableIterableStream` for `InputFile` streaming.
* Connection pool tuned with `limit` and TTL DNS cache analogous to aiohttp connector workaround in upstream.
* Optional `proxy` parameter forwarded to amphp's HTTP client middleware.

A PSR-18 sync session is intentionally not in scope for the initial release. If a future user explicitly needs sync transport (e.g. FPM-only deployment that can't host a polling loop) we will ship it as a separate optional package (`gruven/phpbotgram-psr18-session`) so the core stays single-purpose around amphp.

`Default` sentinel and `Unset` marker:

* `Default` is a final readonly class with a `string $name` property — exactly aiogram's behavior. It implements `JsonSerializable::jsonSerialize()` returning `null` as a defensive default; in practice the serializer always resolves `Default` instances against `$bot->getDefaultProperties()` before encoding ever reaches `json_encode()`. The resolved value is recursively re-processed by `prepareValue()` so a default of e.g. `LinkPreviewOptions(...)` flows through normally.
* `DefaultBotProperties` implements `ArrayAccess` and exposes a typed `get(string $name): mixed` method so `$bot->default->get('parse_mode')` and `$bot->default['parse_mode']` both work. This mirrors upstream's `__getitem__` (`client/default.py:87-88`).
* `Unset` is a readonly singleton (`Unset::instance()`) used as the sentinel for "argument not provided" cases. The serializer strips fields whose value is `Unset::instance()` before validation/encoding.

`BotContextController` & bot binding:

Upstream uses Pydantic's `model_validate(..., context={"bot": bot})` to inject the active `Bot` into deserialized `TelegramObject` instances via `model_post_init(__context)`. PHP readonly classes have no equivalent late-assignment hook, so the port handles bot injection through the serializer and a `withBot()` clone helper.

* `BotContextController` is the abstract parent of `TelegramObject`. It exposes a single optional dependency: the `Bot` that owns the deserialized object. Implementation:

  ```php
  abstract class BotContextController
  {
      public function __construct(public readonly ?Bot $bot = null) {}

      /** Returns a deep clone of $this with $bot rebound recursively into all nested TelegramObject fields. */
      public function withBot(?Bot $bot): static { /* recursive clone via Serializer::rebindBot */ }
  }
  ```

* All generated type classes accept `?Bot $bot = null` as the **last** constructor parameter and forward it via `parent::__construct(bot: $bot)`. Methods and Unions do the same.
* `Serializer::load(string $class, array $data, ?Bot $bot = null)` recursively threads `$bot` into every nested `TelegramObject` it constructs. The traversal table is generated alongside the type class: for each property whose static type is a `TelegramObject` (or list of them), the generator emits a `load()` helper that calls `Serializer::load(NestedType::class, $value, $bot)` before passing it to the parent constructor.
* `BotContextController::withBot()` is used at the dispatcher boundary in three places that mirror upstream:
  1. `Dispatcher::feedUpdate(Bot $bot, Update $update, ...)` re-mounts `$update` to `$bot` when `$update->bot !== $bot`. Upstream's roundtrip-via-JSON workaround (`dispatcher.py:152-161`) becomes `withBot()` (which does the same deep clone, but without the JSON roundtrip — PHP's `clone` is shallow, so each nested `TelegramObject` field carries its own `withBot()` invocation via the generated helper). This is noted in "Open questions / risks" as a hotspot worth profiling.
  2. `Dispatcher::feedRawUpdate(Bot $bot, array $data, ...)` calls `Serializer::load(Update::class, $data, bot: $bot)` directly.
  3. `BaseSession::checkResponse(...)` calls `Serializer::load(Response::class, $payload, bot: $bot)` for outgoing API responses, so e.g. `Message` returned by `sendMessage` already carries the bot for chained shortcut calls.
* Without this plumbing every shortcut call (`$message->answer(...)`, `$callbackQuery->answer(...)`) would null-deref `$this->bot`. The serializer is the only component allowed to write into the bot slot; user code that needs to manually re-bind uses `withBot()`.

Polling loop (mirrors upstream `dispatcher/dispatcher.py` `_listen_updates` / `_polling` / `start_polling` / `run_polling`):

* `Dispatcher::startPolling(Bot ...$bots, int $pollingTimeout = 10, bool $handleAsTasks = true, ?BackoffConfig $backoffConfig = null, ?array $allowedUpdates = null, bool $handleSignals = true, bool $closeBotSession = true, ?int $tasksConcurrencyLimit = null, mixed ...$kwargs): void` — variadic bots first, then named options.
* The kwargs `bot` key is reserved and a `\InvalidArgumentException` is thrown if the caller passes it (mirroring upstream `dispatcher.py:551-555`).
* For each bot the dispatcher first calls `$bot->me()` (cached `User`) and logs `"Run polling for bot @<username> id=<id>"`, then spawns a per-bot polling task via `Amp\async()`.
* A **per-bot** `Amp\Sync\LocalSemaphore` enforces `$tasksConcurrencyLimit` (not shared across bots — matches upstream `asyncio.Semaphore` inside `_polling`).
* All per-bot tasks share a single `Amp\DeferredFuture` named `$stopSignal`. When that future resolves, all polling tasks complete on the next `getUpdates` round.
* `EventLoop::onSignal(SIGINT, …)` + `EventLoop::onSignal(SIGTERM, …)` register handlers when `$handleSignals` is true; unavailable on Windows (requires `ext-pcntl`). The handler logs a "Received <sig> signal" line and resolves `$stopSignal`. Wrapping in `try { ... } catch (\Throwable) {}` mirrors upstream `with suppress(NotImplementedError)`.
* `Router::emitStartup()` and `Router::emitShutdown()` are called once each (around the polling task fan-out) with `bot: $bots[array_key_last($bots)]` plus the merged workflow_data, and recurse into sub-routers (`router.py:274-298`). The injected `bot` parameter is then available to startup/shutdown callbacks as a handler kwarg.
* `Dispatcher::runPolling(...)` is the public sync wrapper that boots the event loop via `Amp\async(...)->await()` then awaits the future returned by `startPolling`. It swallows `\Throwable` only for the keyboard interrupt case (signal-driven graceful exit) so `^C` from a TTY behaves like upstream's `with suppress(KeyboardInterrupt):` block.
* `_listen_updates` analog: per-bot generator that pages through `getUpdates` with exponential `Backoff` retry on `TelegramNetworkError` / `TelegramServerError`. Failed-then-succeeded transition logs the recovery and resets the backoff counter (matches `dispatcher.py:237-244`).
* `Dispatcher::stopPolling(): void` resolves the shared `$stopSignal` and awaits the per-bot tasks. Throws `\RuntimeException('Polling is not started')` if no polling lock is held.

Example call site:

```php
$dispatcher = new Dispatcher();
$bot1 = new Bot($token1);
$bot2 = new Bot($token2);
$dispatcher->runPolling($bot1, $bot2, pollingTimeout: 30, tasksConcurrencyLimit: 10);
```

Webhook response contract (mirrors upstream `dispatcher/dispatcher.py:436-495` + `webhook/aiohttp_server.py:192-208`):

* `Dispatcher::feedWebhookUpdate(Bot $bot, Update|array $update, float $timeout = 55.0, mixed ...$kwargs): ?TelegramMethod` runs the dispatch under a deadline. If the handler resolves within `$timeout` seconds and returns a `TelegramMethod`, that method is returned and the caller (the webhook request handler) is expected to encode it as the HTTP response body (`multipart/form-data` per `webhook/aiohttp_server.py:155-190`). If the deadline expires first, the dispatch continues in the background; a `\Trigger_error("Detected slow response into webhook…", E_USER_WARNING)` is emitted (parity with `RuntimeWarning` upstream); when the background task eventually finishes and the handler returned a `TelegramMethod`, that method is dispatched via `Dispatcher::silentCallRequest($bot, $method)`.
* `Dispatcher::silentCallRequest(Bot $bot, TelegramMethod $method): void` (public **instance** method, not static — diverges from upstream's `@classmethod` since PHPUnit-style static mocking is awkward). Behavior: calls `$bot($method)`, catches `TelegramApiException` only, and logs at error level. All other exceptions propagate. Used by both `_processUpdate` (when a polling handler returns a method) and the webhook slow-response path above.
* `BaseRequestHandler` accepts `bool $handleInBackground = false` (default false for `BaseRequestHandler`; `SimpleRequestHandler` and `TokenBasedRequestHandler` default to `true` to match upstream defaults in `webhook/aiohttp_server.py:215, 250`). When true, the handler responds with empty JSON `{}` immediately and the dispatch is fire-and-forget via `Amp\async()`. When false, the handler awaits the dispatch result and either echoes the returned `TelegramMethod` or sends an empty JSON body.

## Types and methods (codegen)

aiogram ships 341 type classes and 178 method classes generated from `.butcher`. phpbotgram does the same.

### Schema source

* Vendored copy of upstream `.butcher/` lives in `phpbotgram/.butcher/`:
  * `schema/schema.json`
  * `types/<Name>/{entity.json,aliases.yml,replace.yml}`
  * `methods/<name>/{entity.json,default.yml}`
  * `enums/…`
* The schema is updated by syncing from upstream tagged releases. A `scripts/sync-schema.sh` helper performs the rsync from a path or URL.

### Generator (`tools/generator/`)

PHP CLI built with plain `getopt()` (kept dep-free; switch to `symfony/console` only if argument-parsing complexity warrants) + `twig/twig`.

* `bin/generate.php --schema .butcher/schema/schema.json --patches .butcher --out src/`
* Pipeline:
  1. `SchemaLoader` parses `schema.json` + applies per-entity patches.
  2. `TypeResolver` maps Telegram primitive type strings to PHP types:
     * `Integer` → `int`
     * `String` → `string`
     * `Boolean` → `bool`
     * `Float` → `float`
     * `True` → `?bool` (the `True` literal in upstream schema means "this field, when set, is always `true`"; aiogram and we both emit it as nullable `bool` since `true|null` collapses to `?bool` and PHP cannot constrain defaults to literal `true`)
     * `Array of X` → `array` at runtime, annotated with `@var list<X>` PHPDoc and PHPStan `array<int, X>` for level 9 strictness
     * `X or Y` → `X|Y` union
     * Date/time-ish strings handled by a custom `DateTime` subclass of `\DateTimeImmutable` on the `Message.date`-style fields (per aiogram custom `DateTime` field in `aiogram/types/custom.py`); the serializer converts Unix-timestamps both ways
     * Deprecated parameters (those bearing `deprecated:` in `entity.json`) are **emitted** on the constructor with a `#[Deprecated]` attribute and a PHPDoc `@deprecated` tag — matches aiogram's behavior of preserving the constructor signature for backward compatibility. Users migrating an aiogram bot keep working without compile errors; the deprecation surface is purely documentation.
  3. `NameMapper` converts snake_case → camelCase, escapes PHP reserved words (`from` → `fromUser`, `class` → `className`, etc.). Wire-level event/property names remain snake_case (see "Event name conventions" below) so JSON serialization stays byte-compatible with the Telegram API.
  4. `UnionDetector` identifies sealed unions (e.g. `BackgroundFill`) and emits:
     * abstract base class `BackgroundFill` with discriminator field `type`,
     * concrete subclasses (`BackgroundFillSolid`, …),
     * a `BackgroundFillUnion` final class that exposes `public const list<string> Members = [BackgroundFillSolid::class, BackgroundFillGradient::class, …]` plus a static `resolve(array $payload): TelegramObject` dispatcher used by the serializer.
     * For PHPStan: every method/type field whose schema type is the union emits a plain PHP union signature (`BackgroundFillSolid|BackgroundFillGradient|BackgroundFillFreeformGradient`) — no `@phpstan-type` aliases (which would need `@phpstan-import-type` in every consumer file). The verbosity is acceptable since it's generated code.
  5. `ShortcutDetector` reads `aliases.yml` per type — this file **is** codegen input, not a hand-authored trait. The generator emits each alias as a public instance method directly on the type class. Lowering rules for the alias DSL:
     * `self.X` → `$this->X`
     * `assert X` → `if ($X === null) { throw new \LogicException("…"); }`
     * Python ternary `A if B else C` → PHP `($B) ? ($A) : ($C)`
     * `fill:` parameters map to constructor arguments of the target method class; absent parameters fall through to `$method->withFoo(...)` chains or just plain pass-through
     * Alias method body returns the constructed method (e.g. `SendMessage`) so users can `await $message->answer('hi')` (since `$method->emit($bot)` is awaitable / Fiber-friendly) or pass it through to `$bot($message->answer('hi'))`
     This applies to all types that ship `aliases.yml` (Message, CallbackQuery, ChatJoinRequest, InlineQuery, PreCheckoutQuery, ShippingQuery, Update — and a few others; the generator iterates `.butcher/types/*/aliases.yml`).
  6. `HandAuthoredShortcuts` directory `src/Types/Shortcuts/<TypeName>Shortcuts.php` holds *additional* hand-authored helpers that aren't expressible as `aliases.yml` directives — e.g. `Message::contentType()` (computed field), `Message::htmlText()` / `Message::mdText()` (text re-rendering via `TextDecoration`), `Message::asReplyParameters()`, `CallbackQuery::answer()` (which conventionally doesn't read from `aliases.yml`). The generator emits a `use <TypeName>Shortcuts;` trait import inside the generated class only when the corresponding `Shortcuts` trait file exists.
  7. `Renderer` emits PHP files into the target directories, formatted to match php-cs-fixer rules.

### Event name conventions

Telegram update keys (`message`, `edited_message`, `business_connection`, `purchased_paid_media`, …) are snake_case wire-level strings. PHP property names on `Router` and `TelegramEventObserver` are camelCase (`$router->editedMessage`, `$router->businessConnection`). The mapping is two-way:

* `Update::eventType(): string` returns the snake_case key from the payload (`'edited_message'`).
* `Router::$observers` is a `array<string, TelegramEventObserver>` keyed by the snake_case string; the camelCase properties are aliases backed by the same instance: `$this->editedMessage = $this->observers['edited_message'] = new TelegramEventObserver(...)`.

### PHPStan generics layout

`TelegramMethod<TReturn>` carries the return-type contract end-to-end so `$bot($method)` and `$bot->sendMessage(...)` are typed concretely at PHPStan level 9.

* `TelegramMethod`:
  ```php
  /**
   * @template TReturn
   */
  abstract class TelegramMethod extends BotContextController { … }
  ```
* Generated method class:
  ```php
  /**
   * @extends TelegramMethod<Message>
   */
  final readonly class SendMessage extends TelegramMethod { … }
  ```
* `Bot`:
  ```php
  /**
   * @template T
   * @param TelegramMethod<T> $method
   * @return T
   */
  public function __invoke(TelegramMethod $method, ?int $timeout = null): mixed { … }

  public function sendMessage(/* args */): Message { /* return $this(new SendMessage(...)) */ }
  ```
* The generated facade `Bot::sendMessage(...)` declares its concrete return type (`Message`) directly — generics flow only through `Bot::__invoke()` for the polymorphic path; method-specific wrappers use plain return types.
* Runtime: the `public const string ReturnsType = Message::class;` on each method class lives alongside the PHPDoc and is what the serializer actually consults to deserialize the response payload.

### Generated type class shape

```php
namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Client\BotContextController;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Types\Shortcuts\MessageShortcuts;

final class Message extends MaybeInaccessibleMessage
{
    use MessageShortcuts;

    public function __construct(
        public readonly int $messageId,
        public readonly \DateTimeImmutable $date,
        public readonly Chat $chat,
        public readonly ?int $messageThreadId = null,
        public readonly ?DirectMessagesTopic $directMessagesTopic = null,
        public readonly ?User $fromUser = null,   // mapped from "from"
        public readonly ?Chat $senderChat = null,
        // ...
    ) {
        parent::__construct();
    }
}
```

Notes:

* All types inherit from `TelegramObject` (which extends `BotContextController`). The base class holds the optional `?Bot $bot` instance injected during deserialization for shortcuts.
* Final-by-default. Subclassing is allowed only where the schema models a hierarchy (e.g. `MaybeInaccessibleMessage` parent of `Message` and `InaccessibleMessage`).
* `readonly` properties enforce immutability (aiogram models are `frozen=True`). Mutation goes through `withX($value)` clone helpers when needed.
* PHP doesn't allow `from` as a property identifier? It does (`from` is not a reserved keyword in PHP context), but it conflicts with the `from` PHP language construct in some positions. The generator renames `from` → `fromUser` to match aiogram (`from_user`).

### Generated method class shape

```php
namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Client\Default;
use Gruven\PhpBotGram\Types\{ChatIdUnion, LinkPreviewOptions, Message, MessageEntity, ReplyMarkupUnion, ReplyParameters, SuggestedPostParameters};

/**
 * @extends TelegramMethod<Message>
 */
final class SendMessage extends TelegramMethod
{
    public const string ApiMethod = 'sendMessage';
    public const string ReturnsType = Message::class;

    public function __construct(
        public readonly int|string $chatId,
        public readonly string $text,
        public readonly ?string $businessConnectionId = null,
        public readonly ?int $messageThreadId = null,
        public readonly ?int $directMessagesTopicId = null,
        public readonly string|Default|null $parseMode = new Default('parse_mode'),
        public readonly ?array $entities = null,        // list<MessageEntity>
        public readonly LinkPreviewOptions|Default|null $linkPreviewOptions = new Default('link_preview'),
        public readonly ?bool $disableNotification = null,
        public readonly bool|Default|null $protectContent = new Default('protect_content'),
        public readonly ?bool $allowPaidBroadcast = null,
        public readonly ?string $messageEffectId = null,
        public readonly ?SuggestedPostParameters $suggestedPostParameters = null,
        public readonly ?ReplyParameters $replyParameters = null,
        public readonly ?ReplyMarkupUnion $replyMarkup = null,
        // deprecated parameters omitted from the constructor in PHP — accessible via withX helpers if needed
    ) {}
}
```

### Bot facade

The generator emits `src/Bot.php` (~6000 lines, matching the size of upstream `aiogram/client/bot.py` which is 6276 lines). Single class, one method per Telegram API method (178 methods at API 10.0). Each generated method:

```php
public function sendMessage(
    int|string $chatId,
    string $text,
    ?string $businessConnectionId = null,
    // ...
    ?int $requestTimeout = null,
): Message {
    return $this(new SendMessage(
        chatId: $chatId,
        text: $text,
        businessConnectionId: $businessConnectionId,
        // ...
    ), $requestTimeout);
}
```

`Bot::__invoke(TelegramMethod $method, ?int $timeout = null): mixed` is the polymorphic entry point used by method `__await__` emulation. Awaitable-style call site becomes `$bot($method)` or `$method->emit($bot)` in PHP, which matches aiogram's `await method.emit(bot)` semantics.

### Serializer

`Gruven\PhpBotGram\Client\Serializer`:

* `dump(TelegramObject|TelegramMethod $object, Bot $bot, array &$files = []): array` — depth-first walk:
  * Skips `Unset::instance()` values.
  * Resolves `Default` sentinels against `$bot->getDefaultProperties()`.
  * Streams `InputFile` instances into the `$files` collection, replacing the value with `attach://<random>`.
  * Encodes nested `TelegramObject` recursively.
  * Converts `BackedEnum` to its scalar value.
  * Converts `DateTimeInterface` to Unix timestamp.
  * Converts `DateInterval` to `now + interval` Unix timestamp (aiogram does the same for `timedelta`).
* `load(string $class, array $data, ?Bot $bot = null): TelegramObject` — uses constructor reflection to instantiate readonly objects with kwargs from the API payload.
* Union resolution by discriminator (e.g. `type` field for `BackgroundFill`).
* Validation: required fields are checked structurally via PHP type errors; richer validation (e.g. integer ranges) is intentionally not enforced (aiogram itself leans on Telegram-side validation).

## Dispatcher, Router, Filters

`Router`:

* `__construct(?string $name = null)` — same as upstream.
* Owns one `TelegramEventObserver` per Bot API event type. The observers are accessible both as camelCase properties (`$router->message`, `$router->editedMessage`, `$router->channelPost`, `$router->editedChannelPost`, `$router->inlineQuery`, `$router->chosenInlineResult`, `$router->callbackQuery`, `$router->shippingQuery`, `$router->preCheckoutQuery`, `$router->poll`, `$router->pollAnswer`, `$router->myChatMember`, `$router->chatMember`, `$router->chatJoinRequest`, `$router->messageReaction`, `$router->messageReactionCount`, `$router->chatBoost`, `$router->removedChatBoost`, `$router->deletedBusinessMessages`, `$router->businessConnection`, `$router->editedBusinessMessage`, `$router->businessMessage`, `$router->purchasedPaidMedia`, `$router->managedBot`, `$router->guestMessage`, `$router->errors`) and through `$router->observers['<snake_case_name>']`. Plus `startup` / `shutdown` `EventObserver` instances for lifecycle hooks.
* `includeRouter(Router $r)` and `includeRouters(Router ...)`.
* `resolveUsedUpdateTypes(?array $skip = null): array<string>`.
* `propagateEvent(string $type, TelegramObject $event, mixed ...$kwargs): mixed`. Before delegating to observers, the router writes `$kwargs['event_router'] = $this` so middlewares, filters, and handlers can introspect the active router. The dispatcher additionally writes `$kwargs['event_update']` (the wrapping `Update` instance) inside `_listenUpdate` before propagation, exactly as upstream does (`dispatcher.py:281`, `router.py:153`).

`Dispatcher extends Router`:

* `feedUpdate(Bot $bot, Update $update, mixed ...$kwargs): mixed` — re-mounts the update if `$update->bot !== $bot` via `$update->withBot($bot)` (see "BotContextController & bot binding"). Returns the handler's result or `UNHANDLED`.
* `feedRawUpdate(Bot $bot, array $update, mixed ...$kwargs): mixed`.
* `feedWebhookUpdate(Bot $bot, Update|array $update, float $timeout = 55.0, mixed ...$kwargs): ?TelegramMethod` — see "Webhook response contract" above.
* `_processUpdate(Bot $bot, Update $update, bool $callAnswer = true, mixed ...$kwargs): bool` — invokes `silentCallRequest($bot, $result)` when the handler returns a `TelegramMethod` and `$callAnswer === true`. Mirrors upstream `dispatcher.py:303-335`.
* `silentCallRequest(Bot $bot, TelegramMethod $method): void` — public **instance** method (deviation from upstream's `@classmethod` for testability; see "Webhook response contract" rationale). Swallows `TelegramApiException`, logs at error level.
* `startPolling(...)` / `runPolling(...)` / `stopPolling()` — see "Polling loop" above.

`TelegramEventObserver`:

* `register(callable $handler, Filter|callable ...$filters, ?array $flags = null): callable` — appends a `HandlerObject` to `$this->handlers`. The `?array $flags` argument provides per-handler flags; filters can contribute flags via `Filter::updateHandlerFlags(array &$flags): void` (used by `Command`, see "Filters in detail").
* `filter(Filter|callable ...$filters): void` — registers **global** filters that apply to every handler in this observer. Internally stored on a "dummy handler" object whose `check()` is invoked by `Router::propagateEvent` via `checkRootFilters($event, ...$kwargs)`. Used heavily by scenes (`scene.py:405` registers a `StateFilter` on every observer to scope scene-handlers to the active scene state).
* `__invoke(Filter|callable ...$filters, ?array $flags = null): callable` — decorator-style factory matching aiogram's `@router.message(...)`. Returns a closure: `$router->message(MessageF::text()->equals('start'))(fn (Message $m) => …)`. Attribute-based registration (`#[OnMessage(filters: [...])]`) is offered as an optional convenience layer on top of `register()`.
* `outerMiddleware` and `middleware` collections matching upstream `MiddlewareManager`.
* `trigger(TelegramObject $event, mixed ...$kwargs): mixed`.

`Filter`:

* ```php
  abstract class Filter
  {
      /**
       * Accepts the event plus dispatcher kwargs (`event_update`, `event_router`, plus user-supplied workflow data and any earlier filter results).
       *
       * Return values:
       *   - `false` — reject this handler
       *   - `true` — accept, contribute nothing to handler kwargs
       *   - `array<string, mixed>` — accept, **merge into handler kwargs** (the dict keys become named arguments to the handler — exactly as upstream's `dispatcher/event/handler.py:118-123` merges check results into `kwargs`)
       */
      abstract public function __invoke(mixed ...$args): bool|array;

      public function updateHandlerFlags(array &$flags): void {}
      public function not(): Filter { /* returns InvertFilter */ }
      public function and(Filter $other): Filter { /* returns AndFilter */ }
      public function or(Filter $other): Filter { /* returns OrFilter */ }
  }
  ```
* Static helpers: `Filter::all(Filter ...$filters): AndFilter`, `Filter::any(Filter ...$filters): OrFilter`, `Filter::not(Filter $f): InvertFilter`.
* `HandlerObject` (port of `dispatcher/event/handler.py:HandlerObject`) wraps each registered handler with a `CallableObject` that introspects the handler's signature via `ReflectionFunction`/`ReflectionMethod` and binds only the kwargs the handler actually declares. Without that selectivity, the kwarg-injection model (where filters add `command`, `callback_data`, `match`, etc. to the kwargs dict) would force every handler to accept `mixed ...$kwargs`. The reflection step caches the `array<string, true>` of accepted parameter names per callable.

`BaseMiddleware`:

```php
abstract class BaseMiddleware
{
    /** @param callable(TelegramObject, array<string, mixed>): mixed $handler */
    abstract public function __invoke(callable $handler, TelegramObject $event, array $data): mixed;
}
```

## Magic-filter runtime + F-DSL (typed builders)

aiogram's magic-filter surface has two layers:

1. The runtime DSL from the `magic_filter` PyPI package, subclassed by `aiogram/utils/magic_filter.py` to add `.as_(name)` (which lets a filter inject a computed value into handler kwargs). This is the **public** runtime; users import it as `from aiogram import F`. It's what powers `Command(magic=…)`, `F.text.regexp(...).as_("match")`, `F.text.casefold() == "cancel"`, `F.cast(int).as_("value")`, `~F.message.via_bot`, `F.data == "start"`, and the standalone `MagicData(F.event_chat.type == 'private')` filter.
2. The `aiogram/filters/magic_data.py` `MagicData` filter, which resolves a `MagicFilter` against the **middleware data dict** (not against the event), so a handler can scope on `state`, `event_chat`, `event_user`, `bot`, etc.

The port reproduces both layers, plus a code-generated typed-builder façade on top of layer 1 to give the ergonomic, IDE-friendly entry point promised in decision 5.

### Layer 1 — `Utils\MagicFilter\MagicFilter`

Full PHP port of `magic_filter` plus aiogram's `.as_()` extension. ~800 LOC.

* A `MagicFilter` is a lazy chain of operations (attribute access, method call, comparison, transform). Each operation appends to the chain and returns a new `MagicFilter` so the chain is immutable.
* PHP doesn't have `__getattr__`; we use `__get($name): MagicFilter` for `F->text`, `__call($name, $args): MagicFilter` for `F->text->casefold()`, and named methods for terminal operations. Mapping vs Python:
  * `F.text == 'hi'` → `F->text->equals('hi')` (alias `eq`)
  * `F.text != 'hi'` → `F->text->notEquals('hi')` (alias `ne`)
  * `F.text & F.from_user.id == 123` → `F->text->and(F->fromUser->id->eq(123))`
  * `F.text | F.caption` → `F->text->or(F->caption)`
  * `~F.message.via_bot` → `F->message->viaBot->not()`
  * `F.text.casefold()`, `lower()`, `upper()`, `startswith()`, `endswith()`, `contains()`, `regexp(pattern)`, `len()`, `F.cast(int)`, `F.func(callable)` — all map to `__call` operations.
  * `F.text.in_({'a','b'})` → `F->text->in(['a','b'])`
* Terminal evaluation: `MagicFilter::resolve(mixed $value): mixed` walks the chain. If any operation returns null/empty/false, the overall result is null (rejection). The result of the last operation is returned otherwise.
* `MagicFilter::asFilter(): Filter` wraps the chain in a `Filter` instance whose `__invoke($event)` calls `$this->resolve($event)`. Used implicitly when a `MagicFilter` is passed where a `Filter` is expected (via a `Filter::fromMagic()` shim).
* `.as_(string $name): MagicFilter` appends an `AsFilterResultOperation`, which makes the terminal value either `null` (rejected) or `[$name => $value]` (a kwarg dict that the dispatcher merges into handler args). 1-for-1 port of `aiogram/utils/magic_filter.py:9-18`.
* Convenience global: `Gruven\PhpBotGram\F` is a re-export of `MagicFilter::root()` (a class-level singleton) so users write `use Gruven\PhpBotGram\F;` then `F->text->equals('hi')`.

### Layer 2 — `Filters\MagicData`

```php
final class MagicData extends Filter
{
    public function __construct(private readonly MagicFilter $rule) {}

    public function __invoke(TelegramObject $event, array $data): bool|array
    {
        return $this->rule->resolve($data) ? true : false;
    }
}
```

Resolves the `MagicFilter` against the dispatcher's middleware data dict (which includes `bot`, `event_router`, `event_update`, `state`, `event_chat`, `event_user`, plus any user-supplied workflow data). 1-for-1 port of `aiogram/filters/magic_data.py`.

### Layer 3 — `Filters\F\*` typed builders (codegen)

The typed-builder façade gives IDE autocomplete on top of the magic-filter chain. Each builder method composes onto a `MagicFilter` instance.

For each Telegram event type the generator emits a builder class with one static factory per public field:

```php
namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;

final class MessageF
{
    public static function text(): StringField              { return new StringField(MagicFilter::root()->text); }
    public static function caption(): NullableStringField   { return new NullableStringField(MagicFilter::root()->caption); }
    public static function chat(): ChatField                { return new ChatField(MagicFilter::root()->chat); }
    public static function fromUser(): NullableUserField    { return new NullableUserField(MagicFilter::root()->fromUser); }
    public static function date(): DateTimeField            { return new DateTimeField(MagicFilter::root()->date); }
    // ... one per Message field
}
```

Field-builder terminal methods compose onto the `MagicFilter` and return `Filter`:

```php
abstract class BaseField
{
    public function __construct(protected MagicFilter $chain) {}
    public function chain(): MagicFilter       { return $this->chain; }
    public function exists(): Filter           { return $this->chain->asFilter(); }
    public function isNull(): Filter           { return $this->chain->equals(null)->asFilter(); }
    public function func(callable $fn): Filter { return $this->chain->func($fn)->asFilter(); }
    public function asKwarg(string $name): Filter { return $this->chain->as_($name)->asFilter(); }
}

final class StringField extends BaseField
{
    public function equals(string $v): Filter      { return $this->chain->equals($v)->asFilter(); }
    public function notEquals(string $v): Filter   { return $this->chain->notEquals($v)->asFilter(); }
    public function in(array $values): Filter      { return $this->chain->in($values)->asFilter(); }
    public function startsWith(string $v): Filter  { return $this->chain->startswith($v)->asFilter(); }
    public function endsWith(string $v): Filter    { return $this->chain->endswith($v)->asFilter(); }
    public function contains(string $v): Filter    { return $this->chain->contains($v)->asFilter(); }
    public function casefold(): StringField        { return new StringField($this->chain->casefold()); }
    public function lower(): StringField           { return new StringField($this->chain->lower()); }
    public function upper(): StringField           { return new StringField($this->chain->upper()); }
    public function regex(string $pattern): RegexField { return new RegexField($this->chain->regexp($pattern)); }
    public function len(): IntField                { return new IntField($this->chain->len()); }
}

final class RegexField extends BaseField
{
    public function asKwarg(string $name = 'match'): Filter { return $this->chain->as_($name)->asFilter(); }
}

final class IntField extends BaseField { /* equals, notEquals, in, gt, lt, gte, lte, between */ }
final class BoolField extends BaseField { /* isTrue, isFalse */ }
```

Combinators on the resulting `Filter`:

```php
MessageF::text()->casefold()->equals('cancel')                  // Filter
    ->and(MessageF::fromUser()->id()->equals(123))              // Filter
    ->or(MessageF::caption()->contains('hello'));               // Filter

MessageF::text()->regex('^/(?<cmd>\\w+)')->asKwarg('match');    // injects $match into handler kwargs
```

Generator output:

* One builder class per Telegram event type (~30 files) emitted from the schema.
* One nested-object builder per non-leaf field type (`UserField`, `ChatField`, `MessageEntityField`, …). The generator inspects each event type's field list and emits a builder for each nested `TelegramObject` reached transitively.
* `BaseField`, `StringField`, `IntField`, `BoolField`, `RegexField`, `DateTimeField`, `NullableStringField`, `NullableIntField`, `NullableObjectField<T>` are hand-written runtime primitives in `src/Filters/F/`.
* Total generated F-DSL size: comparable to the type catalog itself (~50 files for builders + ~12 hand-written runtime primitives).

### Composition with `Command(magic=…)`

`Command(string $name, ?MagicFilter $magic = null)` accepts a `MagicFilter` (raw, not a typed-builder `Filter` instance) for parity with upstream. The `Command` filter walks the magic-filter chain against the parsed `CommandObject`. Users who prefer the typed builder extract the raw chain via the builder's `chain()` accessor (`MessageF::text()->chain()`).

### Opt-out

Users who don't want the magic-filter surface can implement plain `Filter` subclasses or pass closures (`fn (Message $m): bool|array => str_starts_with($m->text ?? '', 'Hi')`).

## Filters in detail

* `Command(string|RegexPattern|BotCommand ...$values, string $prefix = '/', bool $ignoreCase = false, bool $ignoreMention = false, ?MagicFilter $magic = null)` — port of `aiogram.filters.command.Command`. `CommandObject` is a readonly DTO. `RegexPattern` is a thin wrapper over a precompiled PCRE pattern.
* `CommandStart(?bool $deepLink = null, bool $deepLinkEncoded = false, …)`.
* `CallbackData`: abstract class for callback data payloads.
    * Subclasses declare both prefix and separator via a class attribute: `#[CallbackPrefix(prefix: 'order', sep: ':')]` (the constructor accepts `string $prefix` and `string $sep = ':'`). The CallbackData base validates that `$sep` is not contained in `$prefix` on first use — mirroring upstream `__init_subclass__` checks (`filters/callback_data.py:51-65`).
    * `pack(): string` walks constructor-promoted readonly properties via `ReflectionClass`, encodes each via the type-encoding table below, joins with `$sep`, prepends `$prefix`, and validates `strlen($result) <= MAX_CALLBACK_LENGTH` (64 bytes — Telegram limit, `MAX_CALLBACK_LENGTH = 64`). Throws `\LengthException` if oversized.
    * Type-encoding table (matches `filters/callback_data.py:67-82`):
        * `null` → `''` (empty string)
        * `bool` → `'1'` / `'0'`
        * `int`, `float`, `string` → `(string) $value`
        * `\Stringable` (Decimal-equivalent, BCMath wrappers) → `(string) $value`
        * `\UnitEnum` (backed enum) → `$value->value`
        * `\Ramsey\Uuid\UuidInterface` (if installed) → `->getHex()`; otherwise `(string) $value`
        * Any other type → throws `\InvalidArgumentException` (upstream raises `ValueError`)
        * Encoded value containing `$sep` → throws `\InvalidArgumentException`
    * `static unpack(string $value): static` splits on `$sep`, verifies prefix matches, validates field count equals constructor parameter count, decodes nullable-default fields (`''` → property's declared default if nullable, otherwise raises). 1-for-1 port of `filters/callback_data.py:109-139`.
    * `static filter(?MagicFilter $rule = null): CallbackQueryFilter` returns a Filter that unpacks the callback query payload, applies the optional MagicFilter rule, and injects the result as `callback_data` kwarg.
* `StateFilter(State|StatesGroup|string ...$states)`.
* `ChatMemberUpdatedFilter` mirrors upstream's transitions DSL.
* `ExceptionTypeFilter(string ...$classes)` for error handlers.
* `MagicData(MagicFilter $rule)` — see "Magic-filter runtime + F-DSL" section above.
* `Logic\AndFilter`, `OrFilter`, `InvertFilter` — composable via `Filter::all/any/not`.

## FSM and Scenes

### State / StatesGroup

State definition uses static `State` properties initialized via an **explicit** Reflection-driven bootstrap. PHP cannot intercept static-property access, so lazy initialization on first read is not possible; the user must trigger bootstrap once per subclass before properties are read.

```php
final class OrderStates extends StatesGroup
{
    public static State $waitingProduct;
    public static State $waitingAddress;
    public static State $confirming;
}
OrderStates::bootstrap();   // <-- canonical idiom: trailing call at the end of the class file
```

* `StatesGroup::bootstrap(): void` is idempotent. It uses `ReflectionClass::getProperties(ReflectionProperty::IS_STATIC | ReflectionProperty::IS_PUBLIC)` to enumerate `State`-typed static properties, instantiates a `State` per property with `state: $propertyName, group: static::class`, and assigns it. After bootstrap, the property is non-null.
* Defense in depth: `StateFilter::__construct(State|StatesGroup|class-string<StatesGroup> ...$states)`, `FSMContext::setState(State|string|null $state)`, and `SceneRegistry::add(class-string<Scene>)` all call `StatesGroup::bootstrapIfNeeded($groupClass)` on every passed group reference. So even if the user forgets the trailing call, the framework's first interaction with the group will boot it. The risk is only in raw property reads (`OrderStates::$waitingProduct`) before any framework call, which returns `null` and produces a `TypeError` on use — a fast, obvious failure.
* `bootstrapIfNeeded(class-string<StatesGroup> $group): void` uses a private `array<class-string, true>` flag map to short-circuit re-entry.
* Group nesting: `OrderStates` declares nested groups via a `protected const array Children = [PaymentStates::class];` constant; `bootstrap()` recursively resolves them.
* `default_state` and `any_state` exposed as `State::default()` and `State::any()` static factory methods returning shared singleton `State` instances with `state: null` and `state: '*'` respectively.

Why explicit bootstrap, not static methods (`OrderStates::waitingProduct(): State`)? The decision is locked to property-style access (decision 6) for upstream-feel parity. The trailing `Bootstrap()` call is the smallest possible deviation from that idiom; it's a one-liner that the documentation calls out prominently. A code-style rule (PHP-CS-Fixer custom fixer or static-analyzer assertion) can be added later if call-site forgetting becomes a frequent pitfall.

### FSMContext

```php
final class FSMContext
{
    public function __construct(public readonly BaseStorage $storage, public readonly StorageKey $key) {}
    public function setState(State|string|null $state = null): void;
    public function getState(): ?string;
    public function setData(array $data): void;
    public function getData(): array;
    public function getValue(string $key, mixed $default = null): mixed;
    public function updateData(array $data = [], mixed ...$kwargs): array;
    public function clear(): void;
}
```

### Storage

* `MemoryStorage` — in-process map (per Bot instance unless `withBotId(true)` is set on the key builder), used in tests and for single-process bots.
* `RedisStorage::fromUrl(string $url, ?KeyBuilder $keyBuilder = null, int $stateTtl = 0, int $dataTtl = 0)` — built on `amphp/redis`.
* `MongoStorage::fromUrl(string $url, string $collection = 'aiogram_fsm', ?KeyBuilder $keyBuilder = null)` — built on the sync `mongodb/mongodb` driver wrapped via `Amp\async()` so each storage call yields to other Fibers while the underlying ext-mongodb operation blocks. This intentionally consolidates upstream's two storages (`aiogram.fsm.storage.mongo` Motor-based, deprecated; `aiogram.fsm.storage.pymongo` sync-based, current) into a single PHP `MongoStorage`. Note in README: this deviates from upstream's two-storage layout but matches the "current best practice" path. The deprecated motor-based storage is intentionally not ported.
* `BaseEventIsolation`: `SimpleEventIsolation` (in-process via `Amp\Sync\LocalKeyedMutex`), `RedisEventIsolation` (Redis-based `SET NX EX` locking via `amphp/redis`), `DisabledEventIsolation` (no-op).
* `StorageKey` is a readonly DTO. `DefaultKeyBuilder` matches upstream prefix/separator/`with_bot_id`/`with_business_connection_id`/`with_destiny` configuration.

### FSMContextMiddleware

Behaves identically to upstream: enters the isolation lock, materializes `FSMContext`, injects `state` (FSMContext), `raw_state` (string), into handler data.

### Scenes

* `Scene` abstract class extended by user. Handlers are declared via attributes mapping to event types and optional filters, matching aiogram's decorator-driven Scene API:
  ```php
  final class OrderScene extends Scene
  {
      #[OnEnter]
      public function start(Message $message, SceneManager $scenes): void {
          $message->answer('Choose product');
      }

      #[OnMessage(filters: [/* MessageF::text()->equals('cancel') */])]
      public function cancel(Message $message, SceneManager $scenes): void {
          $scenes->exit();
      }
  }
  ```
* `Scene` event hooks: `#[OnEnter]`, `#[OnExit]`, `#[OnLeave]`, `#[OnBack]`, plus event-type attributes (`#[OnMessage]`, `#[OnCallbackQuery]`, …) that internally translate to `ObserverDecorator` instances mirroring `aiogram.fsm.scene.ObserverDecorator`.
* `SceneRegistry` mirrors aiogram: `(new SceneRegistry($dispatcher))->add(OrderScene::class, …)`.
* `HistoryManager` ports the snapshot/rollback flow including the `scenes_history` destiny key.
* `SceneManager` exposes the same surface as aiogram's `SceneWizard`/`SceneManager` from upstream: `enter(string|class-string $sceneOrState, mixed ...$data)`, `exit()`, `back()`, `retake()`, `goto(string|class-string $target)`. Inside scene handlers it is injected alongside the event.

## Webhook

`BaseRequestHandler`:

* Abstract methods: `resolveBot(Request $req): Bot`, `verifySecret(string $telegramSecret, Bot $bot): bool`, `close(): void` — where `Request` is `Amp\Http\Server\Request` from `amphp/http-server`.
* `handle(Request $req): Response` runs the dispatcher and either responds synchronously with a Telegram method as the reply body (`multipart/form-data`) or returns an empty JSON `{}` and schedules background processing.

`SimpleRequestHandler` (single Bot, optional `?string $secretToken`).

`TokenBasedRequestHandler` (multi-bot, token in URL `/{botToken}`). Validates that the path template contains `{botToken}` at registration time.

`Security\IpFilter` matches upstream — built-in Telegram subnets `149.154.160.0/20` and `91.108.4.0/22`.

`Webhook\Server\AmphpServer::run(BaseRequestHandler $handler, string $path, string $host = '0.0.0.0', int $port = 8443, ?array $tlsOptions = null): void` boots an `amphp/http-server` instance routing POST `$path` to the handler.

A PSR-7/PSR-15 webhook bridge — for users running on top of Symfony HttpKernel, Slim, or Laravel — is intentionally deferred to a separate optional package (`gruven/phpbotgram-psr-webhook`, future). Keeping PSR adapters out of core lets us avoid the entire PSR-7/17/18 dependency stack while still allowing integration to grow on demand.

## Utilities

* `TextDecoration\HtmlDecoration`, `MarkdownDecoration` — port of HTML and MarkdownV2 entity unparsing with surrogate-pair (UTF-16) accounting. PHP equivalents of `add_surrogates`/`remove_surrogates` use `mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')` for entity offset arithmetic. Public helpers exported as `html()` / `md()` static methods, re-exported from `Gruven\PhpBotGram\Html` and `Gruven\PhpBotGram\Md` namespaces for the `aiogram\html.bold(...)` style call sites.
* `DeepLinking` — port of `create_start_link`, `decode_payload`, `encode_payload`.
* `Keyboard\InlineKeyboardBuilder`, `ReplyKeyboardBuilder`.
* `MediaGroup\MediaGroupBuilder`.
* `ChatAction\ChatActionSender` for periodic chat-action emission.
* `CallbackAnswer\CallbackAnswerMiddleware`.
* `Backoff\Backoff` + `BackoffConfig`.
* `Payload`, `Token` (`validateToken`, `extractBotId`), `Link\docsUrl`, `WebApp\WebAppSignature`, `AuthWidget`.
* `I18n` (optional): port of aiogram's gettext-based i18n using `symfony/translation` as the message catalog backend. **Shipped as a separate Composer package `gruven/phpbotgram-i18n`** in a sibling repo (or as a workspace subdir if we adopt a monorepo). The core `gruven/phpbotgram` package neither requires nor suggests `symfony/translation` — keeping a Symfony dependency out of the core install path. Phase 8 ships the i18n skeleton.

## Exceptions

Direct port. Names mirrored 1-to-1. `aiogram.AiogramError` → `Gruven\PhpBotGram\Exceptions\PhpBotGramException`. All `TelegramRetryAfter` etc. carry the `method` (the `TelegramMethod` instance that triggered the failure) and `message` properties. `ClientDecodeError` keeps the original exception and the raw payload.

## Testing strategy

### Layout

```
tests/
├── bootstrap.php
├── Api/
│   ├── Client/                  # Bot, AmphpSession, prepareValue, checkResponse
│   ├── Methods/                 # one test class per method, hitting MockedBot
│   └── Types/                   # serialization round-trips for every type
├── Dispatcher/
│   ├── DispatcherTest.php
│   ├── RouterTest.php
│   ├── Event/                   # TelegramEventObserver, HandlerObject
│   └── Middlewares/
├── Filters/                     # Command, CallbackData, F-DSL, logic
├── Fsm/
│   ├── ContextTest.php, MiddlewareTest.php, SceneTest.php, StateTest.php, StrategyTest.php
│   └── Storage/                 # Memory, Redis, Mongo (skipped without env DSN)
├── Webhook/
├── Utils/
├── Handlers/                    # BaseHandler subclasses
├── Flags/
└── Issues/                      # regression tests
```

### Test infrastructure

* `MockedSession` ports upstream behavior: an in-memory deque of canned responses + a deque of recorded outgoing methods. Exposes `addResult(Response $r): Response` and `getRequest(): TelegramMethod`.
* `MockedBot extends Bot` injects `MockedSession`, exposes `addResultFor(string $methodClass, bool $ok, mixed $result = null, ?string $description = null, int $errorCode = 200, ...): Response` and `getRequest(): TelegramMethod`. Pre-stubs `$bot->me()` to return a fixed `User` (id derived from the test token `42:TEST` so `bot.id === 42`, `username = 'tbot'`, etc.) — matches `tests/mocked_bot.py:63-70`. Without that stub, `Dispatcher::_polling` (which calls `$bot->me()` before entering the loop) cannot run against `MockedBot`.
* `tests/bootstrap.php` configures Revolt's `EventLoop` driver explicitly so async tests use a deterministic loop; before each test, `RunAsync::setUp()` snapshots the loop's pending callback set, and `RunAsync::tearDown()` cancels anything still pending. This is essential because Revolt's event loop is a singleton — leaked callbacks would cross-contaminate test cases.
* Async tests use `\Amp\async(...)->await()` to drive Fibers; helper `runAsync(\Closure $body): mixed` (in-house, ~40 LOC under `tests/Support/RunAsync.php`) drives Revolt's event loop inside a test method, enforcing the per-test cleanup contract above. `amphp/phpunit-util` is incompatible with our PHPUnit 13 baseline, so we don't depend on it.
* Mocking strategy:
    * `MockedSession`/`MockedBot` for HTTP-layer assertions (the upstream pattern).
    * `Dispatcher::silentCallRequest` is mocked via a thin recording proxy: `tests/Support/RecordingDispatcher.php` extends `Dispatcher` and overrides `silentCallRequest()` to push calls onto a public `array $silentCalls` for inspection. This replaces upstream's `unittest.mock.patch("aiogram.dispatcher.dispatcher.Dispatcher.silent_call_request", new_callable=AsyncMock)` idiom, which doesn't translate cleanly to PHP static-method mocking; making `silentCallRequest` a public **instance** method (deviation noted earlier) is what enables this.
    * PHPUnit's `MockBuilder` covers `BaseStorage`, `Filter`, `BaseMiddleware`, etc. where appropriate.
    * Signal-emulation in polling-loop tests is done by directly resolving the dispatcher's shared `$stopSignal` deferred — bypassing `EventLoop::onSignal` since PHPUnit cannot raise OS signals.
* Parameterized cases use PHPUnit data providers.

### External services

* Redis / Mongo tests skip themselves unless `PHPBOTGRAM_REDIS_DSN` / `PHPBOTGRAM_MONGO_DSN` env vars are set, mirroring `pytest --redis=… --mongo=…` from upstream.
* CI provisions ephemeral Redis + Mongo via service containers (GitHub Actions `services:` block) for the full matrix run.

### Coverage gate

* Target: ≥90 % overall, ≥95 % core (Bot, Session, Dispatcher, Router, Filters, FSM).
* `phpunit` configured with branch coverage where supported.

## Examples and documentation

`examples/` — direct ports:

* `echo_bot.php`, `echo_bot_webhook.php`, `echo_bot_webhook_ssl.php`
* `finite_state_machine.php`
* `scene.php`, `quiz_scene.php`
* `multibot.php`
* `error_handling.php`
* `own_filter.php`
* `context_addition_from_filter.php`
* `specify_updates.php`
* `stars_invoice.php`
* `without_dispatcher.php`
* `web_app/`

Each example uses the same patterns as its aiogram counterpart, translated to PHP idioms (`fn` closures or first-class callables for handlers, `Amp\async(...)->await()` for the bootstrap of the polling loop, attribute-based scene declarations).

Documentation: a `docs/` directory with English Markdown sources, structured to mirror upstream's Sphinx layout (`installation`, `quickstart`, `dispatcher`, `filters`, `fsm`, `scenes`, `webhook`, `migration`). Sphinx-equivalent build (mkdocs or VuePress) is out of scope for the initial port — Markdown is enough.

## CI / tooling

* GitHub Actions workflow (`.github/workflows/ci.yml`):
  * Matrix: PHP 8.5 × `lowest`/`highest` composer deps × `Linux`.
  * Jobs: `php-cs-fixer --dry-run`, `phpstan analyze`, `phpunit` (with Redis + Mongo services), `composer validate`, `composer-normalize`.
  * Coverage uploaded to Codecov (matching upstream).
* `Makefile`: `make lint`, `make test`, `make regenerate`, `make examples`.
* Pre-commit hook (optional): runs `php-cs-fixer fix --diff --dry-run` and `phpstan analyze --memory-limit=2G` on staged files.

## Phased roadmap

| Phase | Deliverables | Verification |
|---|---|---|
| **0. Bootstrap** | Composer deps locked (amphp/amp ^3, amphp/http-client ^5, revolt/event-loop ^1, amphp/byte-stream ^2 + ext-mbstring; require-dev: amphp/redis ^2, amphp/http-server ^3, mongodb/mongodb ^2, phpstan ^2.1, twig/twig ^3.10), namespace skeleton, CI scaffolding (GitHub Actions PHP 8.5 + Redis/Mongo services), MockedBot/MockedSession harness with `me()` pre-stub, base `TelegramObject`, `TelegramMethod<T>`, `Default`, `Unset`, `BotContextController` (incl. `withBot()` deep-clone), `TelegramApiServer`, exception tree | `phpunit` runs on empty suite; CI green |
| **1. Foundation** | `Bot` (skeleton, no API methods yet), `BaseSession`, `AmphpSession`, `InputFile` (Buffered/Fs/Url), `Serializer` (dump/load with recursive bot binding), `RequestMiddlewareManager`. Phase 1 hand-writes a minimum `SendMessage` + `Message` + `Bot::sendMessage()` for the Phase 1 smoke test; these are deleted and regenerated in Phase 2. | Manual roundtrip test: `sendMessage` hand-coded against a test bot |
| **2. Codegen** | Copy `.butcher` schema, build `tools/generator/` (incl. all six pipeline stages + the F-DSL templates emitting `Filters/F/*` builders), regenerate all Enums, Types (with `aliases.yml`-derived shortcut methods baked into class bodies), Methods, the Bot facade, the F-DSL builder catalog | Generator emits valid PHP; `phpstan` level 9 passes on `src/`; `phpunit` smoke test instantiates 50 random types end-to-end (serialize → deserialize); generator round-trips with no `git diff` |
| **3. Dispatcher** | `Router`, `Dispatcher`, `TelegramEventObserver` (incl. `filter(...)` global filter), `EventObserver`, `HandlerObject`, `FilterObject`, `CallableObject` (reflection-driven kwarg binding), `Flags`, polling loop (per-bot semaphore, signal handling, startup/shutdown bot injection), webhook response contract (`silentCallRequest`, 55s deferred), `ErrorsMiddleware`, `UserContextMiddleware` | Echo bot example runs against a mock session; `tests/Dispatcher/*` port complete |
| **4. Filters & magic-filter runtime** | `Filter` base, `Command`/`CommandStart`/`CommandObject`, `CallbackData` (full type-encoding table + 64-byte limit), `StateFilter`, `Logic` combinators, `ChatMemberUpdatedFilter`, `ExceptionTypeFilter`, **`Utils\MagicFilter\MagicFilter` runtime port** (~800 LOC), **`Filters\MagicData`**, wire-up of generated `Filters\F\*` builders to the runtime | Port `tests/test_filters/*` + `tests/test_utils/test_magic_filter.py` |
| **5. FSM** | `State`/`StatesGroup` bootstrap (explicit `Bootstrap()` + framework-side `bootstrapIfNeeded` defense), `StorageKey`/`DefaultKeyBuilder`, `FSMContext`, `MemoryStorage`, `RedisStorage`, `MongoStorage`, isolations (`Simple`/`Disabled`/`Redis`), `FSMContextMiddleware`, `Scene`/`SceneRegistry`/`HistoryManager`/`SceneManager` (attributes: `#[OnEnter]`/`#[OnExit]`/`#[OnLeave]`/`#[OnBack]`/`#[OnMessage]`/...) | Port `tests/test_fsm/*` (Redis/Mongo skipped without env DSN) |
| **6. Webhook** | `BaseRequestHandler`, `SimpleRequestHandler`, `TokenBasedRequestHandler`, `IpFilter`, `AmphpServer`, multipart-form response builder, `handleInBackground` defaults | Port `tests/test_webhook/*`, smoke test via amphp/http-server in-process |
| **7. Utils** | TextDecoration (Html/Markdown with UTF-16LE surrogate accounting via ext-mbstring), DeepLinking, Keyboard builders, MediaGroup, ChatAction, CallbackAnswer, Backoff, WebApp/AuthWidget, Token | Port `tests/test_utils/*` |
| **8. Tests + examples** | Port remaining upstream tests, all 12+ example scripts, README quickstart, `gruven/phpbotgram-i18n` skeleton (lives in a sibling repo / monorepo subdir; not bundled into core) | ≥90 % coverage; CI green across full matrix |
| **9. Polish** | Documentation, sample webhook deployment configs (nginx + amphp/http-server example), README, CHANGELOG | Tag `v0.1.0`, public preview |

## Open questions / risks

* **Property hooks vs. readonly trade-off**: PHP 8.4 property hooks can mimic Pydantic computed fields, but they conflict with `readonly`. The initial port sticks to `readonly` everywhere; computed accessors (e.g. `Update::eventType`) become methods (`getEventType(): string`) returning lazily-memoized values stored in a `private array $computed` map.
* **Multipart streaming over HTTP/2**: amphp/http-client supports HTTP/2, but a few Telegram local Bot API forks reportedly mishandle HTTP/2 multipart bodies. The session defaults to HTTP/1.1 for outbound traffic and exposes an `useHttp2: bool` option.
* **Mongo async story**: `mongodb/mongodb` is sync; wrapping with `Amp\async()` works but ties up Fibers on slow queries. The initial port accepts this trade-off; a deferred follow-up may introduce a native amphp Mongo driver if maintenance demands it.
* **PHP 8.5 cadence**: PHP 8.5 is the release branch active at design time. If a contributor needs PHP 8.4, the package's lowest-supported version can be relaxed without API changes — the generator output uses no PHP 8.5-exclusive syntax outside the `|>` operator (used sparingly, replaceable by method chains).
* **Generator maintenance burden**: keeping the codegen in PHP means PHP-side contributors can self-serve schema updates. Upstream's Python butcher remains the canonical reference for any schema patch we don't yet handle; the `scripts/sync-schema.sh` step pulls the latest schema.json plus patches and re-runs the generator.

## Acceptance criteria

* `composer install` on a fresh checkout pulls only the documented dependencies.
* `php examples/echo_bot.php` runs a bot end-to-end via long polling against `api.telegram.org`.
* `php examples/echo_bot_webhook.php` boots an amphp/http-server instance and handles incoming updates.
* `vendor/bin/phpunit` passes on every CI matrix entry without external services; full matrix with Redis + Mongo also passes locally with the env DSNs set.
* `tools/generator/bin/generate.php --schema .butcher/schema/schema.json --out src/` produces a working tree with no `git diff` after running it on a checked-in schema.
* `vendor/bin/phpstan analyze` returns clean at level 9 across `src/` and `tests/`.
* `vendor/bin/php-cs-fixer fix --dry-run` returns no diff.
