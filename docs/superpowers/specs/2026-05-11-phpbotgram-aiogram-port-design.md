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
| Webhook | `amphp/http-server` ^3 native adapter | The webhook subsystem (`Gruven\PhpBotGram\Webhook\*` namespace) is in the core package but `amphp/http-server` itself ships as a **suggest+require-dev** dependency. The `Webhook\Server\AmphpServer` class file `use`s amphp/http-server types via FQN imports; PHP autoload is lazy, so a `composer install --no-dev` consumer who never instantiates `AmphpServer` is unaffected. Polling-only users incur no penalty. Webhook users install `composer require amphp/http-server` themselves (the suggest line points there). A PSR-7/PSR-15 bridge is intentionally deferred to a separate optional package (`gruven/phpbotgram-psr-webhook`, future) |
| Tests | `phpunit/phpunit` ^13.1 + in-house Fiber helper | `amphp/phpunit-util` is pinned to PHPUnit 9 and is incompatible with our PHPUnit 13 baseline; we ship a tiny `RunAsync` test helper (≈40 LOC) that drives Revolt's event loop inside test methods and cleans up pending callbacks in `tearDown` |
| Static analysis | `phpstan/phpstan` ^2.1 level 9 with generics via docblocks | `TelegramMethod<TReturn>` carried in `@template`/`@extends` (see "PHPStan generics layout" below) |
| Style | `friendsofphp/php-cs-fixer` (already configured) | Existing `.php-cs-fixer.dist.php` retained |
| PHP extensions (required) | `ext-mbstring`, `ext-json` | `ext-mbstring` for the `mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')` surrogate-pair accounting in `TextDecoration`; `ext-json` for the serializer |
| PHP extensions (suggested) | `ext-pcntl`, `ext-mongodb` ^2.3, `ext-sockets`, `ext-openssl`, `ext-fileinfo` | `ext-pcntl` enables `EventLoop::onSignal(SIGINT/SIGTERM)` for graceful polling shutdown (unix only); `ext-mongodb` is required by `mongodb/mongodb`; `ext-sockets`/`ext-openssl` are pulled transitively by `amphp/socket`; `ext-fileinfo` is used by `InputFile` mime detection. All listed in `composer.json#suggest` |
| PSR layer | None in public surface | `amphp/http-client` pulls `psr/http-message` transitively but phpbotgram code does not reference it. Webhook signatures are amphp-native (`Amp\Http\Server\Request`/`Response`). A PSR-7/15 bridge is deferred to `gruven/phpbotgram-psr-webhook` (future) |

## Namespace layout

```
Gruven\PhpBotGram\
├── Bot                              # client/bot.py — facade with all 176 API methods (at Bot API 10.0)
├── Client\
│   ├── Session\BaseSession          # client/session/base.py — abstract
│   ├── Session\AmphpSession         # client/session/aiohttp.py — production async
│   ├── Session\Middleware\RequestMiddlewareManager
│   ├── Session\Middleware\BaseRequestMiddleware
│   ├── TelegramApiServer            # client/telegram.py — final readonly class with `public static function production(): self` / `public static function test(): self` factories + `public static function fromBase(string $base): self`. No `PRODUCTION`/`TEST` class constants because PHP 8.5 still doesn't accept `new`-expressions in class-constant initializers (`new` works in top-level `const` / attribute args / default parameter values / static-var initializers only).
│   ├── DefaultBotProperties         # client/default.py
│   ├── BotDefault                   # client/default.py — Default sentinel (renamed: PHP reserves `default` keyword, `class Default` won't parse)
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
│   ├── Event\Bases                  # UNHANDLED, REJECTED, SkipHandler exception, CancelHandler exception, skip() helper
│   ├── Middlewares\BaseMiddleware
│   ├── Middlewares\ErrorsMiddleware
│   ├── Middlewares\UserContextMiddleware
│   └── Flags                        # Flag (DTO), FlagDecorator, FlagGenerator, extractFlags, extractFlagsFromObject, getFlag, checkFlags (PHP camelCase mirroring upstream snake_case helpers)
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
│   ├── FsmStrategy                  # enum (PascalCase per PHP convention; the canonical name in code is `FsmStrategy`, not `FSMStrategy`)
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
│   ├── Server\AmphpServer
│   └── Setup                        # static register(HttpServer, Dispatcher, BaseRequestHandler, path, ...workflow) helper — port of webhook/aiohttp_server.py:22-46 setup_application
├── Handlers\                        # aiogram/handlers/ — class-based handler base classes
│   ├── BaseHandler                  # abstract __invoke()-driven handler with $this->event
│   ├── MessageHandler (exposes fromUser(): ?User and chat(): Chat property-style accessors over $this->event; port of aiogram/handlers/message.py:14-20)
│   ├── MessageHandlerCommandMixin (trait — adds command(): ?CommandObject helper, port of aiogram/handlers/message.py:23-29)
│   ├── CallbackQueryHandler, InlineQueryHandler,
│   │   ChosenInlineResultHandler, PollHandler, ChatMemberHandler (covers both `my_chat_member` and `chat_member` events; both wrap `ChatMemberUpdated`),
│   │   ShippingQueryHandler, PreCheckoutQueryHandler, ErrorHandler
├── Utils\
│   ├── TextDecoration\TextDecoration, HtmlDecoration, MarkdownDecoration
│   ├── DeepLinking, Keyboard, MediaGroup, ChatAction, CallbackAnswer
│   ├── Backoff, BackoffConfig
│   ├── Payload, Token, Link, WebApp\WebAppSignature, AuthWidget
│   ├── I18n                         # shipped as a separate optional package `gruven/phpbotgram-i18n`; kept here for symmetry with upstream's `aiogram/utils/i18n/`
│   └── MagicFilter\MagicFilter      # public DSL: full port of `aiogram/utils/magic_filter.py` (which subclasses pip's `magic_filter`); used by `Command(magic=…)`, `Filters\MagicData`, scenes, ad-hoc filter expressions, plus as runtime substrate of the generated `Filters\F\*` builders
└── Exceptions\
    ├── PhpBotGramException          # AiogramError
    ├── DetailedPhpBotGramException  # DetailedAiogramError
    ├── TelegramApiException, TelegramNetworkException, TelegramBadRequestException,
    │   TelegramConflictException, TelegramForbiddenException,
    │   TelegramNotFoundException, TelegramServerException,
    │   TelegramUnauthorizedException, ClientDecodeException, DataNotDictLikeException
    ├── TelegramRetryAfter, TelegramMigrateToChat, TelegramEntityTooLarge, RestartingTelegram  # upstream names preserved (they don't end in `Error` in aiogram either)
    ├── CallbackAnswerException, SceneException, UnsupportedKeywordArgumentException
    ├── UpdateTypeLookupException
    └── Dispatcher\Event\{SkipHandlerException, CancelHandlerException}  # control-flow exceptions (port of SkipHandler / CancelHandler from event/bases.py)
```

## Async runtime and HTTP layer

The framework is async-first using amphp v3 / Revolt. All session methods are Fiber-aware: their declared return types are plain values (e.g. `Message`), but they may suspend the current Fiber. Callers wanting concurrency wrap calls in `Amp\async(...)`. Handlers may return plain values or `Amp\Future` — the Dispatcher awaits both transparently.

`Gruven\PhpBotGram\Client\Session\BaseSession` (concrete constructor, abstract transport methods — port of `aiogram/client/session/base.py:55-72`):

```php
abstract class BaseSession
{
    public function __construct(
        ?TelegramApiServer $api = null,                          // resolves to TelegramApiServer::production() in the body
        public mixed $jsonLoads = 'json_decode',                 // callable: string → mixed
        public mixed $jsonDumps = 'json_encode',                 // callable: mixed → string
        public float $timeout = 60.0,
    ) {
        $this->api = $api ?? TelegramApiServer::production();
        $this->middleware = new RequestMiddlewareManager();
    }
    public readonly TelegramApiServer $api;
    // ...
}
```

Concrete sessions (`AmphpSession`) forward via `parent::__construct(...)`.

* `abstract public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed;`
* `abstract public function close(): void;`
* `abstract public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream;` — returns an `Amp\ByteStream\ReadableStream`. `Bot::downloadFile` (see Bot facade hand-authored methods below) consumes it via the `read(): ?string` chunk-loop pattern from amphp/byte-stream v2.
* `public function prepareValue(mixed $value, Bot $bot, array &$files, bool $dumpsJson = true): mixed;` — port of `BaseSession.prepare_value` covering `BotDefault` sentinel, `InputFile`, `DateTimeInterface`, enums, lists, dicts, and nested `TelegramObject`. The `&$files` parameter is typed `@param array<string, InputFile> &$files` in PHPDoc; the random-token keys (`secrets.token_urlsafe(10)` upstream → `random_bytes(10)` base64url-encoded in PHP) are used by the multipart writer to wire `attach://<token>` references to file streams. **Recursion contract**: the top-level invocation uses `$dumpsJson = true`, which causes nested list/dict values to be `json_encode()`'d into a single string suitable for the multipart form field. All recursive calls into list/dict elements pass `$dumpsJson = false` so nested objects stay as PHP arrays until the top-level wrap. Mirrors upstream `session/base.py:200-233` where `_dumps_json=False` is threaded through the recursive walk. **Null-filtering rule**: when recursing into lists and dicts, entries whose recursive `prepareValue` call returns `null` are **dropped** from the result. This is the mechanism that elides `BotDefault('parse_mode')` resolved to `null` (no default set on the bot) from outbound payloads — without it, the wire would carry `"parse_mode": null` literals where upstream omits the key entirely.
* `public function checkResponse(Bot $bot, TelegramMethod $method, int $statusCode, string $content): Response;` — port of `BaseSession.check_response` mapping HTTP status + Telegram error codes to typed exceptions.
* `public private(set) RequestMiddlewareManager $middleware;` — public read with private-set asymmetric visibility (PHP 8.4); chained around `makeRequest`. The session's constructor instantiates the manager once; consumers add/remove middlewares via `$session->middleware->register(...)`.

`AmphpSession`:

* Built on `Amp\Http\Client\HttpClientBuilder`.
* Implements `multipart/form-data` body via `Amp\ByteStream\ReadableIterableStream` for `InputFile` streaming.
* Connection pool tuned with `limit` and TTL DNS cache analogous to aiohttp connector workaround in upstream.
* Optional `proxy` parameter forwarded to amphp's HTTP client middleware.

A PSR-18 sync session is intentionally not in scope for the initial release. If a future user explicitly needs sync transport (e.g. FPM-only deployment that can't host a polling loop) we will ship it as a separate optional package (`gruven/phpbotgram-psr18-session`) so the core stays single-purpose around amphp.

`BotDefault` sentinel and `Unspecified` marker:

Note on naming: PHP reserves `default` and `unset` as language keywords (case-insensitive), so `class Default` and `class Unset` both fail to parse even when fully namespaced. The upstream `Default` and `UNSET` symbols are therefore renamed to `BotDefault` and `Unspecified` in the PHP port. The semantics are unchanged.

* `BotDefault` is a final readonly class with a `string $name` property — exactly aiogram's `Default` behavior (`aiogram/client/default.py:13-37`). Its `JsonSerializable::jsonSerialize()` **throws** `\LogicException("BotDefault sentinel reached json_encode without being resolved: {$this->name}")`. This is intentional fail-loud behavior: the serializer (`Serializer::dump()`) is the only allowed encoder for `TelegramMethod` payloads and it always resolves `BotDefault` instances against `$bot->getDefaultProperties()` before encoding. If a `BotDefault` somehow escapes to native `json_encode()`, the loud failure prevents silent emission of `parse_mode: null` (or similar) where the configured default should have been substituted. The resolved value is recursively re-processed by `prepareValue()` so a default of e.g. `LinkPreviewOptions(...)` flows through normally. **Equality**: PHP `===` is identity for objects; upstream's `__eq__` compares by `$name`. The PHP port exposes a `BotDefault::equals(BotDefault $other): bool` helper (and overrides `JsonSerializable` only) so user code comparing sentinels uses `$a->equals($b)` rather than `===`. Documented in the class docblock.
* `DefaultBotProperties` implements `ArrayAccess` and exposes a typed `get(string $name): mixed` method so `$bot->default->get('parse_mode')` and `$bot->default['parse_mode']` both work. This mirrors upstream's `__getitem__` (`client/default.py:87-88`). The constructor runs an aggregation step (port of upstream `__post_init__` at `client/default.py:67-85`): when any of `linkPreviewIsDisabled`/`linkPreviewPreferSmallMedia`/`linkPreviewPreferLargeMedia`/`linkPreviewShowAboveText` is set and the top-level `linkPreview` is null, a `LinkPreviewOptions` instance is materialized from those individual flags and assigned to `$this->linkPreview`. The aggregation runs once during construction.
* `Unspecified` is a readonly singleton (`Unspecified::instance()`) used as the sentinel for "argument not provided" cases — port of upstream `UNSET`. The serializer strips fields whose value is `Unspecified::instance()` before validation/encoding.
* For grep-translating external libraries that imported aiogram's `UNSET_PARSE_MODE`, `UNSET_DISABLE_WEB_PAGE_PREVIEW`, `UNSET_PROTECT_CONTENT` back-compat constants (`aiogram/types/base.py:50-52`), the same names are re-exported from `Gruven\PhpBotGram\Types` as singleton `BotDefault` instances.

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

* All generated type classes accept `?Bot $bot = null` as the **last** constructor parameter. This single trailing parameter is **not** promoted (no `public readonly` prefix); the `readonly ?Bot $bot` property is owned by the root `BotContextController` and re-declaring it as a promoted property in a child would fail at runtime with `Cannot modify readonly property` when `parent::__construct` tries to assign through the parent's already-promoted slot. The child constructor body forwards via `parent::__construct($bot)`. Methods, Unions, and the worked-example `Message` follow the same shape.
* `Serializer::load(string $class, array $data, ?Bot $bot = null)` recursively threads `$bot` into every nested `TelegramObject` it constructs. The traversal table is generated alongside the type class: for each property whose static type is a `TelegramObject` (or list of them), the generator emits a `load()` helper that calls `Serializer::load(NestedType::class, $value, $bot)` before passing it to the parent constructor.
* `BotContextController::withBot()` is used at the dispatcher boundary in three places that mirror upstream:
  1. `Dispatcher::feedUpdate(Bot $bot, Update $update, ...)` re-mounts `$update` to `$bot` when `$update->bot !== $bot`. Upstream's roundtrip-via-JSON workaround (`dispatcher.py:152-161`) becomes `withBot()` (which does the same deep clone, but without the JSON roundtrip — PHP's `clone` is shallow, so each nested `TelegramObject` field carries its own `withBot()` invocation via the generated helper). This is noted in "Open questions / risks" as a hotspot worth profiling.
  2. `Dispatcher::feedRawUpdate(Bot $bot, array $data, ...)` calls `Serializer::load(Update::class, $data, bot: $bot)` directly.
  3. `BaseSession::checkResponse(...)` calls `Serializer::load(Response::class, $payload, bot: $bot)` for outgoing API responses, so e.g. `Message` returned by `sendMessage` already carries the bot for chained shortcut calls.
* Without this plumbing every shortcut call (`$message->answer(...)`, `$callbackQuery->answer(...)`) would null-deref `$this->bot`. The serializer is the only component allowed to write into the bot slot; user code that needs to manually re-bind uses `withBot()`.
* For grep-translating aiogram code that uses `obj.as_(bot)` (`aiogram/client/context_controller.py:18-26`), `BotContextController` exposes `as_(?Bot $bot): static` — but with a **deliberate behavioral break** from upstream. Upstream's `as_` is an **in-place mutation** of `self._bot` returning `self`; the spec's `as_()` returns a deep clone via `withBot()`. This is because the PHP types are `readonly` and an in-place rebind is not legal. The behavioral break is documented loudly: any aiogram code that does `obj.as_(bot)` and continues to operate on the original (unbound) `$obj` will silently miss the rebind. Suggested migration path for users porting code: replace `$msg->as_($bot)->answer(...)` with `($msg = $msg->withBot($bot))->answer(...)`. The `as_` name is preserved for grep-friendly translation but the doc-block warns about the semantic difference.

### Mutable type variant

`Gruven\PhpBotGram\Types\MutableTelegramObject` is a `TelegramObject` subclass without the `readonly` class modifier (port of `aiogram/types/base.py:38-41 MutableTelegramObject`). Used in two places:

1. **Schema-driven emission**: types whose `replace.yml` declares `bases: [MutableTelegramObject]` are emitted as `MutableTelegramObject` subclasses by the codegen. This applies to the `InputMedia*` family (the upstream schema currently has ~5-10 such patches) where the wire-level type needs to be mutable so user code can patch fields after construction.
2. **Hand-authored runtime builders**: keyboard builders, media-group builders (`Utils/Keyboard/`, `Utils/MediaGroup/`) extend `MutableTelegramObject` directly for the same reason.

Generated schema types that don't ship a `bases:` patch remain `final readonly` and extend the default `TelegramObject`.

Polling loop (mirrors upstream `dispatcher/dispatcher.py` `_listen_updates` / `_polling` / `start_polling` / `run_polling`):

* PHP forbids parameters after a variadic, so the entry point accepts an options DTO + variadic bots:
  ```php
  public function startPolling(PollingOptions $options, Bot ...$bots): void;
  public function runPolling(PollingOptions $options, Bot ...$bots): void;
  ```
  where `PollingOptions` is a readonly DTO with `pollingTimeout: int = 10`, `handleAsTasks: bool = true`, `backoffConfig: BackoffConfig = new BackoffConfig(minDelay: 1.0, maxDelay: 5.0, factor: 1.3, jitter: 0.1)` (matches upstream `DEFAULT_BACKOFF_CONFIG` at `dispatcher.py:35`), `allowedUpdates: array|Unspecified|null = Unspecified::instance()`, `handleSignals: bool = true`, `closeBotSession: bool = true`, `tasksConcurrencyLimit: ?int = null`, and an associative `array<string, mixed> $workflowData = []` for the named-kwargs equivalent. A static `PollingOptions::default()` returns these defaults so callers can write `$dp->runPolling(PollingOptions::default(), $bot1, $bot2)`. `$allowedUpdates` has three states: `Unspecified::instance()` (default — call `resolveUsedUpdateTypes()` to auto-detect, parity with upstream `UNSET`); `null` (send no `allowed_updates` to `getUpdates`); `array<string>` (the explicit list passed verbatim).
* `BackoffConfig` defaults match upstream `DEFAULT_BACKOFF_CONFIG = BackoffConfig(min_delay=1.0, max_delay=5.0, factor=1.3, jitter=0.1)` (`dispatcher.py:34`).
* The kwargs `bot` key is reserved and a `\InvalidArgumentException` is thrown if the caller passes it (mirroring upstream `dispatcher.py:551-555`).
* For each bot the dispatcher first calls `$bot->me()` (cached `User`) and logs `"Run polling for bot @<username> id=<id>"`, then spawns a per-bot polling task via `Amp\async()`.
* A **per-bot** `Amp\Sync\LocalSemaphore` (instantiated inside the `_polling` invocation for each bot) enforces `$tasksConcurrencyLimit` — matches upstream `asyncio.Semaphore` constructed inside `_polling` (`dispatcher.py:386-388`).
* In-flight handler tasks are tracked **on the Dispatcher** (not per-bot) in `private array<int, Future> $handleUpdateTasks` — one collection shared across all bots that the dispatcher is running, mirroring upstream `self._handle_update_tasks: set[asyncio.Task]` (`dispatcher.py:103, 408-409`). Each spawned task installs `Future::finally(fn () => unset($this->handleUpdateTasks[$id]))` for self-cleanup. On shutdown the polling driver cancels still-pending tasks via `Future::cancel()` before returning, mirroring `dispatcher.py:614-619`.
* All per-bot tasks share a single `Amp\DeferredFuture` named `$stopSignal`. When that future resolves, all polling tasks complete on the next `getUpdates` round.
* `EventLoop::onSignal(SIGINT, …)` + `EventLoop::onSignal(SIGTERM, …)` register handlers when `$handleSignals` is true; unavailable on Windows (requires `ext-pcntl`). The handler logs a "Received <sig> signal" line and resolves `$stopSignal`. Wrapping in `try { ... } catch (\Throwable) {}` mirrors upstream `with suppress(NotImplementedError)`.
* `Router::emitStartup()` and `Router::emitShutdown()` are called once each (around the polling task fan-out) with `bot: $bots[array_key_last($bots)]` plus the merged workflow_data, and recurse into sub-routers (`router.py:274-298`). The injected `bot` parameter is then available to startup/shutdown callbacks as a handler kwarg.
* `Dispatcher::runPolling(...)` is the public sync wrapper that boots the event loop via `Amp\async(...)->await()` then awaits the future returned by `startPolling`. It swallows `\Throwable` only for the keyboard interrupt case (signal-driven graceful exit) so `^C` from a TTY behaves like upstream's `with suppress(KeyboardInterrupt):` block.
* `_listen_updates` analog: per-bot generator that pages through `getUpdates` with exponential `Backoff` retry on `TelegramNetworkError` / `TelegramServerError`. Failed-then-succeeded transition logs the recovery and resets the backoff counter (matches `dispatcher.py:237-244`).
* `Dispatcher` holds a `private \Amp\Sync\LocalMutex $runningLock;` initialized once. `startPolling()`/`runPolling()` acquire this mutex on entry (and release on exit via `finally`) to prevent two polling drivers from running concurrently against the same dispatcher — matches upstream `self._running_lock: asyncio.Lock` at `dispatcher.py:100, 558`. `Dispatcher::stopPolling(): void` resolves the shared `$stopSignal` and awaits the per-bot tasks. Throws `\RuntimeException('Polling is not started')` if the polling is not active — checked via a `private bool $isPolling = false;` flag toggled inside the `runningLock` critical section (amphp v3's `Amp\Sync\LocalMutex` exposes only `acquire(): Lock` and lock-handle `release()`, not an `isLocked()` probe; the boolean flag is the simplest cross-version check). Mirrors upstream `dispatcher.py:497-509`.

Example call site:

```php
$dispatcher = new Dispatcher();
$bot1 = new Bot($token1);
$bot2 = new Bot($token2);
$dispatcher->runPolling(
    new PollingOptions(pollingTimeout: 30, tasksConcurrencyLimit: 10),
    $bot1,
    $bot2,
);
```

Webhook response contract (mirrors upstream `dispatcher/dispatcher.py:436-495` + `webhook/aiohttp_server.py:192-208`):

* `Dispatcher::feedWebhookUpdate(Bot $bot, Update|array $update, float $_timeout = 55.0, mixed ...$kwargs): ?TelegramMethod` runs the dispatch under a deadline. The leading-underscore parameter name preserves upstream's "discouraged-but-accepted" naming convention (`dispatcher.py:440`). Two branches:
  * **In-time branch** — handler resolves within `$_timeout` seconds: if the handler returns a `TelegramMethod`, that method is returned to the caller (the webhook request handler) which encodes it as the HTTP response body (`multipart/form-data` per `webhook/aiohttp_server.py:155-190`). If the handler returns anything else, `null` is returned and the adapter writes an empty JSON `{}` body.
  * **Deadline-expired branch** — handler still running when the 55s deadline passes: a `trigger_error("Detected slow response into webhook…", E_USER_WARNING)` is emitted (parity with the `RuntimeWarning` upstream raises). The dispatch continues in the background; when the background task eventually finishes and the handler returned a `TelegramMethod`, that method is dispatched via `Dispatcher::silentCallRequest($bot, $method)` to keep Telegram happy. The webhook adapter responds immediately with an empty JSON `{}` body so Telegram doesn't time out.
* `Dispatcher::silentCallRequest(Bot $bot, TelegramMethod $method): void` (public **instance** method, not static — diverges from upstream's `@classmethod` since PHPUnit-style static mocking is awkward). Behavior: calls `$bot($method)`, catches `TelegramApiException` only, and logs at error level. All other exceptions propagate. Used by both `_processUpdate` (when a polling handler returns a method) and the webhook slow-response path above.
* `BaseRequestHandler` accepts `bool $handleInBackground = false` (default false for `BaseRequestHandler`; `SimpleRequestHandler` and `TokenBasedRequestHandler` default to `true` to match upstream defaults in `webhook/aiohttp_server.py:215, 250`). When true, the handler responds with empty JSON `{}` immediately and the dispatch is fire-and-forget via `Amp\async()`. When false, the handler awaits the dispatch result and either echoes the returned `TelegramMethod` or sends an empty JSON body.

## Types and methods (codegen)

The Bot API 10.0 schema contains 305 type entities, 176 method entities, and 34 enum entities (verified by `ls .butcher/{types,methods,enums} | wc -l` against the vendored schema), which expand to ~341 type files, ~178 method files, and ~35 enum files once the generator emits the discriminated-union helper classes (e.g. `*Union` aliases) and abstract bases for sealed hierarchies (e.g. `BackgroundFill`, `InputMedia`). phpbotgram emits a one-to-one PHP equivalent. A `tools/generator/SCHEMA_INFO.md` file pins the exact pre-generation entity counts and the post-generation file counts; the codegen acceptance test asserts that the emitted file tree matches.

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
     * `True` → `?bool`. Telegram's schema uses the `True` literal to mean "this field, when present, is always sent as `true`". PHP cannot constrain a parameter type to the literal `true` value, so the generator emits a nullable `bool` at the type level and the serializer doesn't enforce truthiness on input — Telegram always sends `true` when the field is set, so a runtime guard would be defensive against a non-issue
     * `Array of X` → `array` at runtime, annotated only with `@var list<X>` PHPDoc (PHPStan ^2 level 9-compatible). The previous draft also annotated `array<int, X>`; that duplication confuses the analyzer because `list<X>` is the stricter form. Use `list<X>` only
     * `X or Y` → `X|Y` union
     * Date/time-ish strings handled by a custom `DateTime` subclass of `\DateTimeImmutable` on the `Message.date`-style fields (per aiogram custom `DateTime` field in `aiogram/types/custom.py`); the serializer converts Unix-timestamps both ways
     * Deprecated parameters (those bearing `deprecated:` in `entity.json`) are **emitted** on the constructor with a PHPDoc `@deprecated <since> — <reason>` tag. PHP's native `#[\Deprecated]` attribute does **not** target parameters (allowed targets: class, function, method, class-constant, constant — verified), so the deprecation lives in the docblock. IDEs and PHPStan honor `@deprecated` for parameter-level warnings, which matches aiogram's behavior of preserving the constructor signature for backward compatibility. Users migrating an aiogram bot keep working without compile errors; the deprecation surface is purely documentation.
  3. `NameMapper` converts snake_case → camelCase. PHP allows most "reserved" tokens (`from`, `class`, `function`, `list`, `match`, …) as **identifier** positions (property names, method names, named arguments, parameters), so the rename table is **not** primarily a fail-closed reserved-keyword filter. Instead, the renames serve two purposes: (a) upstream-style consistency (e.g. `from` → `fromUser` mirrors aiogram's `from_user`, easing grep-translation), and (b) avoiding identifiers that *are* keyword-position-only and thus produce parse errors when used as class names (`default`, `unset` — handled separately at the type level via the `BotDefault`/`Unspecified` renames). Known per-field renames from the current schema: `from` → `fromUser`, `class` → `className`, `list` → `items`, `function` → `fn`. The mapper still aborts the generator with an explicit error on any token that PHP would actually reject in identifier position (rare; verified against PHP 8.5's grammar). Wire-level event/property names remain snake_case (see "Event name conventions" below) so JSON serialization stays byte-compatible with the Telegram API.
  4. `TypeOverrideApplier` consumes `replace.yml` per entity. The patches cover three operation classes, applied **after** primitive type resolution and **before** the union detector:

     a. `annotations.<field>.parsed_type` — override a single field's resolved type:
        ```yaml
        annotations:
          date:
            parsed_type:
              type: std
              name: DateTime
        ```
        `parsed_type.type` is one of:
          * `std` — a plain class/scalar name (`DateTime`, `datetime.datetime`, `datetime.timedelta`, `str`, …). The mapper translates Python builtins to PHP equivalents (`datetime.datetime` → `\DateTimeImmutable`, `datetime.timedelta` → `\DateInterval`, `str` → `string`, `int` → `int`, etc.).
          * `entity` — a reference to another schema entity: `references: { category: types|methods|enums, name: <EntityName> }`. The generated PHP type is the corresponding class FQN.
          * `union` — a composite union of `items: [{type: std|entity|union, ...}, ...]`. Used by `InputMediaAnimation::media`, `InputMediaAudio::media`, etc. to express `InputFile | str`. The emitted PHP union spans every resolved member type.

     b. `annotations.<field>.required` — flip the required/optional flag for a field (rare; used to mark schema fields as required in the framework even when the wire makes them optional).

     c. **`bases:` (top-level)** — overrides the parent class(es) of the emitted type. Two distinct use-cases:
        * **Mutable lift**: `bases: [MutableTelegramObject]` makes the emitted class extend `MutableTelegramObject` (non-readonly variant) instead of the default `TelegramObject`. The patch is applied to **abstract parent types** (e.g. `InputMedia`, `InlineKeyboardMarkup`, `KeyboardButton`) rather than the concrete children; the generator must therefore **propagate `bases:` through sealed-union inheritance chains** when emitting concrete subclasses. The current schema applies this lift to 16 entities: `KeyboardButton`, `MenuButton`, `ReplyKeyboardMarkup`, `PassportElementError`, `InputMedia` (and inherited by `InputMediaAnimation`, `InputMediaAudio`, etc.), `InlineKeyboardMarkup`, `InlineKeyboardButton`, `ChatPermissions`, `MessageEntity`, `LabeledPrice`, `ReplyKeyboardRemove`, `InlineQueryResult` (and inherited by all `InlineQueryResult*` types), `ForceReply`, `KeyboardButtonPollType`, `BotCommand`, `InputMessageContent`.
        * **Custom parent**: `bases: [Chat]` on `ChatFullInfo` (extends the canonical `Chat` type to share fields), `bases: [ABC]` on `InputFile`. The patch token `ABC` (Python's `abc.ABC`) is rewritten by the generator into PHP's `abstract` class modifier — i.e. `class InputFile extends ABC` (Python) becomes `abstract class InputFile` (PHP) with no explicit parent. These force a non-default parent class (or abstract modifier) that the generator must thread through into the emitted `class X extends <Parent>` declaration.

     The two-value mental model (`TelegramObject` vs `MutableTelegramObject` only) is incomplete; the generator's `bases:` resolver consults the patch directly and emits whatever class is referenced. There are 18 total `bases:` patches in the current schema (16 mutable lifts + `ChatFullInfo` + `InputFile`).

     Without these patches, fields whose schema declares `Integer` for unix-timestamp dates would emit as raw integers, `InputMedia*::media` fields would lose their `InputFile|string` union, `ChatFullInfo` would not inherit `Chat`'s fields, and the keyboard-builder runtime types would be readonly (preventing the mutation patterns aiogram uses).
  5. `UnionDetector` identifies sealed unions (e.g. `BackgroundFill`) and emits:
     * abstract base class `BackgroundFill` with discriminator field `type`,
     * concrete subclasses (`BackgroundFillSolid`, …),
     * a `BackgroundFillUnion` final class that exposes `public static function members(): array { return [BackgroundFillSolid::class, BackgroundFillGradient::class, …]; }` (with PHPDoc `@return list<class-string<BackgroundFill>>` so PHPStan infers the precise list type — a class constant declared as `public const array Members` would erase to `array<int, mixed>` at the consumer site because PHPStan only reads `@var` from the constant's docblock, not the elements). Plus a static `resolve(array $payload): TelegramObject` dispatcher used by the serializer to discriminate by the `type` field.
     * For PHPStan: every method/type field whose schema type is the union emits a plain PHP union signature (`BackgroundFillSolid|BackgroundFillGradient|BackgroundFillFreeformGradient`) — no `@phpstan-type` aliases (which would need `@phpstan-import-type` in every consumer file). The verbosity is acceptable since it's generated code.
  6. `ShortcutDetector` reads `aliases.yml` per type — this file **is** codegen input, not a hand-authored trait. The generator emits each alias as a public instance method directly on the type class. The `aliases.yml` DSL covers:
     * **YAML anchors and aliases** (`&assert-chat`, `*assert-chat`, `<<: *fill-answer`) — handled by the YAML parser. The loader (e.g. `symfony/yaml`) expands anchors before any codegen sees them. `<<:` map-merge keys propagate fill defaults across aliased methods (e.g. `reply` inherits `answer`'s fill block and adds `reply_parameters`).
     * **`ignore:` directives** (`ignore: &ignore-reply [reply_to_message_id]`) — the listed parameter names are **dropped from the alias method's signature**; they remain on the underlying method class but are not exposed on the shortcut.
     * **`fill:` directives** — each key/value pair maps a target-method constructor argument to either a `self.X` expression (resolved at call time from the type instance) or a literal. The generated alias signature is `<target_method_params> minus <fill keys> minus <ignore keys>`. The body builds the target method with the fill values plus the user-supplied arguments.
     * **`code:` directives** — arbitrary preamble executed before constructing the method, written in a Python-like DSL. YAML block-scalar style (`|`) preserves newlines, so `code:` blocks can be multi-statement. Lowering rules:
       * `self.X` → `$this->X` (attribute access)
       * `self.X()` → `$this->X()` (method call — required for `reply_parameters: self.as_reply_parameters()` patterns in `Message::reply` / `InaccessibleMessage::reply`)
       * `self.X(arg)` → `$this->X(arg)`
       * `assert X is not None, "msg"` → `if ($this->X === null) { throw new \LogicException("msg"); }`
       * Python ternary `A if B else C` → PHP `($B) ? ($A) : ($C)`
       * `True` / `False` / `None` → `true` / `false` / `null`
     * **Codegen ordering rule**: hand-authored `Types/Shortcuts/<TypeName>Shortcuts.php` traits are detected first; the alias lowering step then **skips** any method name already supplied by the trait (so a hand-authored `Message::contentType()` cannot be silently shadowed by an aliases.yml `contentType` entry, which would also be a fatal PHP-level "method already declared" error). If the trait method's name collides with an aliases.yml entry of the same name, the generator aborts with an explicit error message naming the conflict and asks the maintainer to choose one source of truth. This ordering ensures alias bodies that reference shortcut helpers (e.g. `Message::reply`'s `self.as_reply_parameters()` resolved via the hand-authored `asReplyParameters()` in `MessageShortcuts`) resolve correctly.
     * **Return value**: alias method body returns the constructed method class (e.g. `SendMessage`) bound to `$this->bot` via `$method->bindBot($this->bot)`. Users may `$message->answer('hi')->emit()` (Fiber-friendly) or pass it to `$bot($message->answer('hi'))`. The bind step is what makes the awaitable-on-method idiom (`await message.answer(...)` in aiogram) translate cleanly.

     **Worked example A — `Message::answer`** (no `ignore:`; verbatim from `.butcher/types/Message/aliases.yml:1-8`):
     ```yaml
     answer:
       method: sendMessage
       code: &assert-chat |
         assert self.chat is not None, "This method can be used only if chat is present in the message."
       fill: &fill-answer
         chat_id: self.chat.id
         message_thread_id: self.message_thread_id if self.is_topic_message else None
         business_connection_id: self.business_connection_id
     ```
     emits roughly:
     ```php
     public function answer(
         string $text,
         string|BotDefault|null $parseMode = new BotDefault('parse_mode'),
         // ... all sendMessage params except chat_id, message_thread_id,
         //     business_connection_id (fill-keys). reply_to_message_id IS still
         //     accepted on Message::answer (the deprecated parameter; only reply()
         //     drops it via ignore:).
     ): SendMessage {
         if ($this->chat === null) {
             throw new \LogicException('This method can be used only if chat is present in the message.');
         }
         return (new SendMessage(
             chatId: $this->chat->id,
             text: $text,
             messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
             businessConnectionId: $this->businessConnectionId,
             parseMode: $parseMode,
             // ...
         ))->bindBot($this->bot);
     }
     ```

     **Worked example B — `Message::reply`** (uses `ignore:` AND `<<:` merge inheriting `answer`'s fill block; abbreviated from `aliases.yml:10-17` — the upstream YAML names a second anchor `&fill-reply` on the merged `fill:` block which the snippet omits for brevity):
     ```yaml
     reply:
       method: sendMessage
       code: *assert-chat
       fill:
         <<: *fill-answer
         reply_parameters: self.as_reply_parameters()
       ignore: &ignore-reply
         - reply_to_message_id
     ```
     emits roughly:
     ```php
     public function reply(
         string $text,
         string|BotDefault|null $parseMode = new BotDefault('parse_mode'),
         // ... sendMessage params except chat_id, message_thread_id,
         //     business_connection_id, reply_parameters (fill-keys)
         //     AND except reply_to_message_id (dropped by ignore:)
     ): SendMessage {
         if ($this->chat === null) {
             throw new \LogicException('This method can be used only if chat is present in the message.');
         }
         return (new SendMessage(
             chatId: $this->chat->id,
             text: $text,
             messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
             businessConnectionId: $this->businessConnectionId,
             replyParameters: $this->asReplyParameters(),
             parseMode: $parseMode,
             // ...
         ))->bindBot($this->bot);
     }
     ```
     The schema currently ships `aliases.yml` for exactly these 11 types: Message, InaccessibleMessage, User, Chat, CallbackQuery, ChatJoinRequest, InlineQuery, PreCheckoutQuery, ShippingQuery, ChatMemberUpdated, Sticker. (Note: `Update` does **not** have `aliases.yml` despite intuition — events route through dispatcher observers rather than type shortcuts.) The generator iterates `.butcher/types/*/aliases.yml` and **silently skips** types whose directory lacks the file — no error, no warning. The remaining ~294 types simply receive no alias methods.
  7. `DefaultsResolver` consumes `methods/<name>/default.yml` per method entity. Each file is a flat YAML mapping of method-parameter names (snake_case, wire-level) to `DefaultBotProperties` field names. Example from `methods/sendMessage/default.yml`:
     ```yaml
     disable_web_page_preview: link_preview_is_disabled
     parse_mode: parse_mode
     protect_content: protect_content
     link_preview_options: link_preview
     ```
     The renderer threads each entry into the corresponding constructor-parameter default expression on the generated method class. For `parseMode`, the default becomes `new BotDefault('parse_mode')` — a runtime sentinel that the serializer's `prepareValue()` resolves against `$bot->default->get('parse_mode')` before encoding. For parameters absent from `default.yml`, the constructor default is `null`. This stage runs after `ShortcutDetector` and before `HandAuthoredShortcuts`.
  8. `HandAuthoredShortcuts` directory `src/Types/Shortcuts/<TypeName>Shortcuts.php` holds *additional* hand-authored helpers that aren't expressible as `aliases.yml` directives — e.g. `Message::contentType()` (computed field), `Message::htmlText()` / `Message::mdText()` (text re-rendering via `TextDecoration`), `Message::asReplyParameters()`, `CallbackQuery::answer()` (which conventionally doesn't read from `aliases.yml`). The generator emits a `use <TypeName>Shortcuts;` trait import inside the generated class only when the corresponding `Shortcuts` trait file exists.
  9. `Renderer` emits PHP files into the target directories, formatted to match php-cs-fixer rules.

### Event name conventions

Telegram update keys (`message`, `edited_message`, `business_connection`, `purchased_paid_media`, …) are snake_case wire-level strings. PHP property names on `Router` and `TelegramEventObserver` are camelCase (`$router->editedMessage`, `$router->businessConnection`). The mapping is two-way:

* `Update::eventType(): string` returns the snake_case key from the payload (`'edited_message'`). The implementation is **hand-authored, not generated** — it copies the exact upstream `if … elif …` chain from `types/update.py:163-223` so the priority order matches: `message`, `edited_message`, `channel_post`, `edited_channel_post`, `inline_query`, `chosen_inline_result`, `callback_query`, `shipping_query`, `pre_checkout_query`, `poll`, `poll_answer`, `my_chat_member`, `chat_member`, `chat_join_request`, `message_reaction`, `message_reaction_count`, `chat_boost`, `removed_chat_boost`, `deleted_business_messages`, `business_connection`, `edited_business_message`, `business_message`, `purchased_paid_media`, `guest_message`, `managed_bot`. This order is observable: if an `Update` payload carries both `message` and `edited_message` (Telegram doesn't normally send both at once, but tests may construct such fixtures), the `message` branch wins and handlers registered on the `edited_message` observer never fire. This matches upstream's `if … elif …` chain exactly, and the spec freezes the order.
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
  final class SendMessage extends TelegramMethod { … }   // not declared `readonly class` because TelegramMethod's chain isn't readonly (so MutableTelegramObject can subclass alongside); properties are individually `public readonly`
  ```
* `Bot`:
  ```php
  /**
   * @template TReturn
   * @param TelegramMethod<TReturn> $method
   * @return TReturn
   */
  public function __invoke(TelegramMethod $method, ?int $timeout = null): mixed { … }

  public function sendMessage(/* args */): Message { /* return $this(new SendMessage(...)) */ }
  ```
* The generated facade `Bot::sendMessage(...)` declares its concrete return type (`Message`) directly — generics flow only through `Bot::__invoke()` for the polymorphic path; method-specific wrappers use plain return types.
* Runtime: each method class declares `public const string ReturnsType = Message::class;`. For PHPStan level 9 the const is annotated `@var class-string<Message>` so the analyzer can tie `ReturnsType` to the `@template TReturn` chain on `TelegramMethod`. The serializer consults this const at runtime to deserialize the response payload.

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
        // ... (all other schema fields)
        ?Bot $bot = null,                          // last param, NOT promoted; forwarded to parent
    ) {
        parent::__construct($bot);
    }
}
```

Notes:

* All types inherit from `TelegramObject` (which extends `BotContextController`). The base class holds the optional `?Bot $bot` instance injected during deserialization for shortcuts. **`TelegramObject` itself is *not* declared `readonly class`** — only its properties are readonly. This is deliberate: a `readonly` class cannot be extended by a non-`readonly` class, so making `TelegramObject` a readonly class would break `MutableTelegramObject extends TelegramObject`. Property-level readonly enforcement covers the immutability requirement for the 95% of schema types that are immutable, while leaving the door open for the small mutable family.
* Final-by-default. Subclassing is allowed only where the schema models a hierarchy (e.g. `MaybeInaccessibleMessage` parent of `Message` and `InaccessibleMessage`).
* `readonly` properties enforce immutability (aiogram models are `frozen=True`). Mutation goes through `withX($value)` clone helpers when needed.
* PHP does allow `from` as a property identifier (via constructor promotion: `public function __construct(public readonly ?string $from = null) {}` parses fine; a bare `public readonly ?string $from = null;` outside the constructor would error with "Readonly property cannot have default value" — that's a separate readonly-property constraint, not a keyword conflict). The rename `from` → `fromUser` is purely to mirror upstream's `from_user` for grep-friendly translation.

### Generated method class shape

```php
namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Client\BotDefault;
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
        public readonly string|BotDefault|null $parseMode = new BotDefault('parse_mode'),
        public readonly ?array $entities = null,        // list<MessageEntity>
        public readonly LinkPreviewOptions|BotDefault|null $linkPreviewOptions = new BotDefault('link_preview'),
        public readonly ?bool $disableNotification = null,
        public readonly bool|BotDefault|null $protectContent = new BotDefault('protect_content'),
        public readonly ?bool $allowPaidBroadcast = null,
        public readonly ?string $messageEffectId = null,
        public readonly ?SuggestedPostParameters $suggestedPostParameters = null,
        public readonly ?ReplyParameters $replyParameters = null,
        public readonly ?ReplyMarkupUnion $replyMarkup = null,
        // Deprecated parameters are emitted with a @deprecated PHPDoc tag.
        // (PHP's native #[\Deprecated] attribute does NOT target parameters — allowed targets are
        // class/function/method/class-constant/constant only — so the deprecation surface lives in
        // PHPDoc; IDEs and PHPStan honor the @deprecated tag for parameter-level warnings.)
        // This preserves the upstream constructor surface so users porting an aiogram script
        // that uses e.g. allow_sending_without_reply=True keep working without compile errors.
        /** @deprecated since API 7.0 — use reply_parameters instead */
        public readonly ?bool $allowSendingWithoutReply = null,
        ?Bot $bot = null,   // NOT promoted: parent BotContextController owns `public readonly ?Bot $bot`;
                            // this is the only non-promoted constructor parameter and forwards to parent
    ) {
        parent::__construct($bot);
    }
}
```

### Bot facade

The generator emits `src/Bot.php` (~6000 lines, matching the size of upstream `aiogram/client/bot.py` which is 6276 lines). Single class, one method per Telegram API method (176 methods at API 10.0 per the verified schema entity count). Each generated method:

```php
public function sendMessage(
    int|string $chatId,
    string $text,
    ?string $businessConnectionId = null,
    // ...
    ?int $timeout = null,                    // request timeout passed to BaseSession::makeRequest
): Message {
    return $this(new SendMessage(
        chatId: $chatId,
        text: $text,
        businessConnectionId: $businessConnectionId,
        // ...
    ), $timeout);
}
```

`Bot::__invoke(TelegramMethod $method, ?int $timeout = null): mixed` is the polymorphic entry point used by method `__await__` emulation. Awaitable-style call site becomes `$bot($method)` or `$method->emit($bot)` in PHP, which matches aiogram's `await method.emit(bot)` semantics.

`TelegramMethod::emit(?Bot $bot = null): mixed` — when `$bot` is null, the method falls back to the bot bound during alias-method construction (`bindBot()`). If both are null, throws `\LogicException("This method is not mounted to any bot instance, please call it explicitly with bot instance \`\$bot(\$method)\` or mount method to a bot instance \`\$method->bindBot(\$bot)\` and then call it \`\$method->emit()\`.")`. Matches upstream `__await__` behavior at `methods/base.py:84-93`.

**Hand-authored Bot methods** live in `src/Client/BotShortcuts.php` (a trait `use`d by the generated `Bot` class). The trait carries full method bodies; the contract is documented via the matching `BotShortcutsContract` interface that the trait implements so `Bot` keeps a unified type signature. Mirrors upstream `aiogram/client/bot.py:344-490` non-codegen surface:

```php
interface BotShortcutsContract
{
    /**
     * Bot ID extracted from the token. Named `getId()` (not `id()`) because upstream `bot.id` is a Python @property:
     * users grep-translating aiogram code that writes `bot.id` would see PHP return a method handle instead
     * of the int. The explicit `getId()` makes the difference loud. The Token::extractBotId helper validates
     * the token format (`<digits>:<rest>`) and throws PhpBotGramException on malformed input at construction time.
     */
    public function getId(): int;
    /** Returns a closure-based "with"-block: runs the body, then closes the bot session on exit. Mirrors upstream Bot.context(auto_close=True) at client/bot.py:357-369. */
    public function context(bool $autoClose = true): callable;
    public static function current(): ?Bot;
    public static function setCurrent(?Bot $bot): void;
    public function me(): User;
    public function downloadFile(File|string $fileOrPath, mixed $destination = null, int $chunkSize = 65536): ?string;
    public function download(Downloadable $object, mixed $destination = null, int $chunkSize = 65536): ?string;
}

trait BotShortcuts
{
    public function getId(): int { /* extract from token via Token::extractBotId */ }
    public function context(bool $autoClose = true): callable { /* closure-based with-block */ }
    public static function current(): ?Bot { /* FiberLocal accessor */ }
    public static function setCurrent(?Bot $bot): void { /* FiberLocal mutator */ }
    public function me(): User { /* cached getMe() */ }
    public function downloadFile(File|string $fileOrPath, mixed $destination = null, int $chunkSize = 65536): ?string { /* … */ }
    public function download(Downloadable $object, mixed $destination = null, int $chunkSize = 65536): ?string { /* resolve File then downloadFile */ }
}
```

The `id` property is lazily extracted from the token via `Token::extractBotId()`. `context()` returns a callable that the caller invokes as `($bot->context())(function () use ($bot) { … });` — analogous to `async with bot.context()` upstream. The body runs, then the bot's session is closed via `await $bot->session()->close()`. `current()`/`setCurrent()` use `Revolt\EventLoop\FiberLocal` to expose a per-Fiber "current bot" — a separate convenience layer ported from `aiogram/utils/mixins.py:ContextInstanceMixin`, unrelated to the session-cleanup helper. `me()` is cached on first call (matches upstream behavior). `downloadFile`/`download` consume `BaseSession::streamContent` and either write to the supplied stream/path or buffer and return the body string.

### Serializer

`Gruven\PhpBotGram\Client\Serializer`:

* `dump(TelegramObject|TelegramMethod $object, Bot $bot, array &$files = []): array` — depth-first walk:
  * Skips `Unspecified::instance()` values (the sentinel singleton).
  * Resolves `BotDefault` sentinels against `$bot->getDefaultProperties()`.
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

* Constructor: `__construct(?BaseStorage $storage = null, FsmStrategy $fsmStrategy = FsmStrategy::UserInChat, ?BaseEventIsolation $eventsIsolation = null, bool $disableFsm = false, ?string $name = null, mixed ...$workflowData)`. The `$workflowData` named-args become part of the per-handler kwargs dict the dispatcher merges into every handler invocation. Mirrors upstream `dispatcher.py:43-99`. **Convention**: all non-variadic parameters are documented as **named-only** — call as `new Dispatcher(storage: $s, fsmStrategy: ..., name: '...')`. PHP doesn't enforce keyword-only, but the spec contract is that positional calls are not supported (so future parameter insertions don't silently break callers). Upstream enforces this via the Python `*` separator.
* `Dispatcher` implements `ArrayAccess` (`offsetGet/offsetSet/offsetExists/offsetUnset`) plus a typed `get(string $key, mixed $default = null): mixed` against `$workflowData`, exactly mirroring upstream's `__getitem__`/`__setitem__`/`__delitem__`/`get` on `Dispatcher`. So users can do `$dp['my_dep'] = $foo;` and handlers receive `my_dep` as a kwarg.
* `feedUpdate(Bot $bot, Update $update, mixed ...$kwargs): mixed` — re-mounts the update if `$update->bot !== $bot` via `$update->withBot($bot)` (see "BotContextController & bot binding"). Returns the handler's result or `UNHANDLED`. Note: `withBot()` is a structural deep-clone that reuses already-validated field values. Upstream's `Update.model_validate(update.model_dump(), context={"bot": bot})` instead re-runs all Pydantic validators on the JSON roundtrip. This is a documented semantic difference (see "Open questions / risks").
* `feedRawUpdate(Bot $bot, array $update, mixed ...$kwargs): ?TelegramMethod` — deserializes the raw payload to `Update` via `Serializer::load(Update::class, $update, bot: $bot)` then delegates to `_feedWebhookUpdate(...)` (the same internal flow used by `feedWebhookUpdate`). Matches upstream `dispatcher.py:186-195` where `feed_raw_update` is implemented as a thin shim around `_feed_webhook_update`. The return is the optional `TelegramMethod` (so webhook callers can wire the result as the HTTP response body).
* `feedWebhookUpdate(Bot $bot, Update|array $update, float $_timeout = 55.0, mixed ...$kwargs): ?TelegramMethod` — see "Webhook response contract" above. The `$_timeout` leading-underscore convention is preserved across both call-site listings.
* `_processUpdate(Bot $bot, Update $update, bool $callAnswer = true, mixed ...$kwargs): bool` — invokes `silentCallRequest($bot, $result)` when the handler returns a `TelegramMethod` and `$callAnswer === true`. Mirrors upstream `dispatcher.py:303-335`.
* `silentCallRequest(Bot $bot, TelegramMethod $method): void` — public **instance** method (deviation from upstream's `@classmethod` for testability; see "Webhook response contract" rationale). Swallows `TelegramApiException`, logs at error level.
* `startPolling(...)` / `runPolling(...)` / `stopPolling()` — see "Polling loop" above.

`TelegramEventObserver`:

* PHP forbids parameters after a variadic, so the observer's registration API is:
  ```php
  public function register(callable $handler, ?array $flags = null, Filter|callable ...$filters): callable;
  public function filter(Filter|callable ...$filters): void;
  public function __invoke(?array $flags = null, Filter|callable ...$filters): callable;   // decorator factory
  ```
  PHP forbids positional arguments after a named argument, so the call site is **all-positional**: `$router->message->register($handler, ['priority' => 1], $f1, $f2)` — pass `null` for flags when only filters are needed: `$router->message->register($handler, null, $f1, $f2)`. The `?array $flags` argument provides per-handler flags; filters can additionally contribute flags via `Filter::updateHandlerFlags(array &$flags): void` (used by `Command`, see "Filters in detail").
* `filter(...)` registers **global** filters that apply to every handler in this observer. Internally stored on a "dummy handler" object whose `check()` is invoked by `Router::propagateEvent` via `checkRootFilters($event, ...$kwargs)`. Used heavily by scenes (`scene.py:405` registers a `StateFilter` on every observer to scope scene-handlers to the active scene state).
* `__invoke(?array $flags, Filter|callable ...$filters): callable` — decorator-style factory matching aiogram's `@router.message(...)`. Returns a closure: `$register = $router->message(null, MessageF::text()->equals('start')); $register(fn (Message $m) => …);`. Attribute-based registration (`#[OnMessage(filters: [...], flags: [...])]`) is offered as an optional convenience layer on top of `register()`.
* `outerMiddleware` and `middleware` collections matching upstream `MiddlewareManager`.
* `trigger(TelegramObject $event, mixed ...$kwargs): mixed`.

`Filter`:

* ```php
  abstract class Filter
  {
      /**
       * Concrete filters declare named parameters; the dispatcher's CallableObject adapter
       * introspects the signature via ReflectionMethod and binds only the kwargs the filter
       * actually declares (mirroring HandlerObject.check's signature filtering at
       * dispatcher/event/handler.py:62-66). Example concrete signatures:
       *
       *   Command::__invoke(Message $message, Bot $bot): bool|array
       *   StateFilter::__invoke(TelegramObject $event, ?string $raw_state = null): bool
       *   ChatMemberUpdatedFilter::__invoke(ChatMemberUpdated $update): bool
       *
       * Return values:
       *   - `false` — reject this handler
       *   - `true` — accept, contribute nothing to handler kwargs
       *   - `array<string, mixed>` — accept, **merge into handler kwargs** (the dict keys become
       *     named arguments to the handler — exactly as upstream's
       *     dispatcher/event/handler.py:118-123 merges check results into kwargs)
       */
      // Subclasses declare a typed signature; the base does not constrain it.
      // The dispatcher invokes filters via CallableObject::call($event, ...$kwargsAsNamed)
      // which uses Reflection to bind only the named parameters the subclass declares.

      public function updateHandlerFlags(array &$flags): void {}
      public function not(): Filter { /* returns InvertFilter */ }
      public function and(Filter $other): Filter { /* returns AndFilter */ }
      public function or(Filter $other): Filter { /* returns OrFilter */ }
  }
  ```
  Note: `Filter::__invoke` is intentionally **not** declared with a rigid abstract signature. The reflection adapter that dispatches both filters and handlers binds named kwargs by parameter name, so concrete filter classes are free to declare whichever named parameters they consume. The abstract surface is implicit: every filter is callable, and every call returns `bool|array<string, mixed>`. **Validator contract** (mirroring upstream `HandlerObject.check` at `dispatcher/event/handler.py:114-123`): the dispatcher reads each filter's return value and normalizes it to a `(bool $passed, array<string, mixed> $extraKwargs)` tuple. Rules: `false` or `null` → rejected; `true` → passed with no kwargs; an array → passed, array is treated as `$extraKwargs` and merged into the kwargs dict; any non-empty value the user accidentally returns from a non-Filter callable is treated as a hard error (`\TypeError`). `CallableObject::__construct` does **not** type-check the return type at registration (PHPStan annotations are the static contract); it only caches the parameter list for kwarg binding. Runtime enforcement of the return shape happens in the dispatcher's `HandlerObject::check` consumer.
* Static helpers: `Filter::all(Filter ...$filters): AndFilter`, `Filter::any(Filter ...$filters): OrFilter`, `Filter::invertOf(Filter $f): InvertFilter`. The static negation helper is named `invertOf` rather than `not` because PHP cannot declare both a static method `Filter::not(Filter)` and an instance method `$filter->not()` with the same name in one class.
* **Flags attachment mechanism** (port of `aiogram/dispatcher/flags.py`):

Upstream attaches flags by mutating the callback function: `value.aiogram_flag = {...}` (`dispatcher/flags.py:53-57`). PHP closures and `Closure` instances do not support arbitrary attribute assignment; the framework instead uses a hybrid of PHP attributes + a `\WeakMap<\Closure|object, Flag[]>`:

* Method/function handlers declared in a class can wear PHP attributes: `#[Flag(name: 'chat_action', value: 'typing')]` on the handler method. `FlagDecorator::register(callable $cb, Flag $flag): \Closure` is the imperative entry point used when there's no class to attach an attribute to. **PHP's `\WeakMap` requires object keys**; string-callable and array-callable (`[$obj, 'method']`) inputs are lifted into closures via `\Closure::fromCallable($cb)` before storage. The lifted closure is returned so callers can re-register it for dispatch.
* The framework's `extractFlagsFromObject(callable $h): array<string, mixed>` first scans for `#[Flag]` attributes via `ReflectionFunction`/`ReflectionMethod`, then — if the callable is a `Closure` or `__invoke`-able object — consults the `\WeakMap` for any programmatically-attached flags, and merges them into the handler's flags array (mirroring `dispatcher/flags.py:extract_flags_from_object`). String-callable inputs (`'my_function'`) carry only attribute-based flags.
* `flags = new FlagGenerator()` is the PHP top-level singleton matching upstream `from aiogram import flags`. Usage like `$flags->chatAction('typing')` returns a `FlagDecorator` instance that, when applied to a handler via `FlagDecorator::__invoke(callable): callable`, stores the flag in the `\WeakMap`. PHP equivalent of the upstream `@flags.chat_action("typing")` decorator:
  ```php
  $router->message->register(
      $flags->chatAction('typing')(
          fn (Message $message) => $message->answer('processing...'),
      ),
  );
  ```
  Or via attribute on a method:
  ```php
  #[Flag(name: 'chat_action', value: 'typing')]
  public function onMessage(Message $message): void { /* … */ }
  ```
* Helpers: `getFlag(HandlerObject $h, string $name, mixed $default = null): mixed` and `checkFlags(HandlerObject $h, MagicFilter $rule): bool` — direct ports.

`HandlerObject` (port of `dispatcher/event/handler.py:HandlerObject`) wraps each registered handler with a `CallableObject` that introspects the handler's signature via `ReflectionFunction`/`ReflectionMethod` and binds only the kwargs the handler actually declares. Without that selectivity, the kwarg-injection model (where filters add `command`, `callback_data`, `match`, etc. to the kwargs dict) would force every handler to accept `mixed ...$kwargs`. The reflection step caches a descriptor `{params: array<string, true>, varKw: bool}` per callable. When `varKw === true` (handler declares `mixed ...$kwargs`), **all** kwargs are forwarded unfiltered, mirroring upstream `CallableObject._prepare_kwargs` (`dispatcher/event/handler.py:62-66`).

### Injected dispatcher kwargs (reference table)

Every middleware that writes into `$data` is part of the contract — handlers and filters can declare any of these names as parameters and the reflection step binds them automatically. Names match upstream wire-level kwargs (snake_case) because they're tied to Python ports:

| Key | Type | Writer | When |
|---|---|---|---|
| `bot` | `Bot` | dispatcher | always (the active bot for this update) |
| `bots` | `list<Bot>` | dispatcher | start-polling/run-polling startup data only |
| `dispatcher` | `Dispatcher` | dispatcher | start-polling/run-polling startup data only |
| `event_router` | `Router` | `Router::propagateEvent` (`router.py:153`) | before each observer call |
| `event_update` | `Update` | `Dispatcher::_listenUpdate` (`dispatcher.py:281`) | before propagation |
| `event_context` | `EventContext` | `UserContextMiddleware` | every update except `error` |
| `event_from_user` | `?User` | `UserContextMiddleware` (legacy alias) | every update except `error` |
| `event_chat` | `?Chat` | `UserContextMiddleware` (legacy alias) | every update except `error` |
| `event_thread_id` | `?int` | `UserContextMiddleware` (legacy alias) | every update except `error` |
| `router` | `Router` | `Router::emitStartup` / `Router::emitShutdown` (`router.py:282, 295`) | startup/shutdown only |
| `state` | `FSMContext` | `FSMContextMiddleware` | when FSM enabled |
| `raw_state` | `?string` | `FSMContextMiddleware` | when FSM enabled |
| `fsm_storage` | `BaseStorage` | `FSMContextMiddleware` (`fsm/middleware.py:36`) | when FSM enabled |
| `scenes` | `ScenesManager` | `SceneRegistry` | when scenes are wired |
| `handler` | `HandlerObject` | `TelegramEventObserver::trigger` | inside handler dispatch |
| `event` | `ErrorEvent` | `ErrorsMiddleware` | error observer only — the `ErrorEvent` DTO carries `Throwable $exception` and `Update $update` |
| `exception` | `\Throwable` | `ErrorsMiddleware` | error observer only — alias for `$event->exception` |
| user-supplied via `Dispatcher::__construct(..., mixed ...$workflowData)` or `$dp['key'] = $value` | `mixed` | dispatcher constructor / ArrayAccess | always |

`EventContext` is a readonly DTO carrying `?Chat $chat`, `?User $user`, `?int $threadId`, `?string $businessConnectionId`. Mirrors upstream's `aiogram.dispatcher.middlewares.user_context.EventContext`. The `businessConnectionId` field is accessible only via `event_context.businessConnectionId` — it is **not** written as a top-level kwarg (the deprecated `event_business_connection_id` key in upstream's `MiddlewareData` TypedDict is documentation residue, not populated by the live middleware).

`BaseMiddleware`:

```php
abstract class BaseMiddleware
{
    /** @param callable(TelegramObject, array<string, mixed>): mixed $handler */
    abstract public function __invoke(callable $handler, TelegramObject $event, array $data): mixed;
}
```

`MiddlewareManager` (port of `aiogram/dispatcher/middlewares/manager.py`):

```php
final class MiddlewareManager implements \Countable, \IteratorAggregate, \ArrayAccess
{
    public function register(BaseMiddleware $middleware): BaseMiddleware { /* appends */ }
    public function unregister(BaseMiddleware $middleware): void;
    /**
     * Callable as both an instance-accepting registrar AND a decorator factory:
     *   $router->message->outerMiddleware(new MyMiddleware());      // instance form
     *   $register = $router->message->outerMiddleware();             // decorator-factory form
     *   $register(new MyMiddleware());
     */
    public function __invoke(?BaseMiddleware $middleware = null): BaseMiddleware|callable;
    // \ArrayAccess requires `mixed $offset` for parameter compatibility (PHP enforces invariant params on interface impl).
    // The implementation runtime-asserts the offset is an int and throws \TypeError otherwise.
    public function offsetGet(mixed $offset): BaseMiddleware;
    public function offsetExists(mixed $offset): bool;
    public function offsetSet(mixed $offset, mixed $value): void;   // appends if $offset is null; throws otherwise
    public function offsetUnset(mixed $offset): void;
    public function count(): int;
    public function getIterator(): \Iterator;
    /** Wraps a chain of middlewares around the terminal handler — used internally by TelegramEventObserver::trigger. */
    public static function wrapMiddlewares(iterable $middlewares, callable $handler): callable;
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
  * `~F.text` → `F->text->not()`
  * `F.text.casefold()`, `lower()`, `upper()`, `startswith()`, `endswith()`, `contains()`, `regexp(pattern)`, `len()`, `F.func(callable)` — all map to `__call` operations.
  * `F.cast(int)` (which upstream exposes as a `MagicFilter` instance method, not a field accessor) → `F->cast(intval(...))` via a public `MagicFilter::cast(callable $fn): MagicFilter` method. The `intval(...)` form is PHP 8.5 **first-class callable syntax** (returns a `Closure` reference to `intval`, not an invocation); for non-Closure callables passed in, the implementation wraps via `\Closure::fromCallable(...)` so the chain always holds a `Closure`.
  * `F.text.in_({'a','b'})` → `F->text->in(['a','b'])`
* Terminal evaluation: `MagicFilter::resolve(mixed $value): mixed` walks the chain operation-by-operation. Per-operation rules:
  * **Attribute / method-call operations** propagate the value as-is (including `false`, `0`, empty strings — these are valid values mid-chain).
  * **Comparison operations** (`equals`, `notEquals`, `in`, `gt`, `lt`, `startswith`, `endswith`, `contains`, `regexp`, …) return `bool` and pass that boolean forward. A `false` result here does *not* short-circuit the chain — it just becomes the new value.
  * **Terminal acceptance** is determined only by the *final* value: `MagicFilter::asFilter()` wraps the chain so the result `null` or empty `Iterable` becomes a `Filter` reject; any other value (including `false`, `0`, `''`, or a populated array) becomes accept.
  * `.as_(name)` via `AsFilterResultOperation` (port of `aiogram/utils/magic_filter.py:9-18`) rejects only when value is `null` or `(Iterable && empty)`; otherwise wraps the value as `{$name => $value}`. So `F->text->startswith('hi')->as_('matched')` against `text='no'` resolves to `['matched' => false]` (accepted with the boolean payload) — matching upstream `magic_filter` semantics exactly.
* `MagicFilter::asFilter(): Filter` wraps the chain in a `Filter` instance whose `__invoke($event)` calls `$this->resolve($event)`. Used implicitly when a `MagicFilter` is passed where a `Filter` is expected (via a `Filter::fromMagic()` shim).
* `.as_(string $name): MagicFilter` appends an `AsFilterResultOperation`, which makes the terminal value either `null` (rejected) or `[$name => $value]` (a kwarg dict that the dispatcher merges into handler args). 1-for-1 port of `aiogram/utils/magic_filter.py:9-18`.
* `MagicFilter::root(): MagicFilter` returns an unbound chain seed (a fresh `MagicFilter` instance with an empty operation chain) — equivalent to upstream's bare `F`. Used by typed-builder factories that need a fresh root per call (`MessageF::text()` does `new StringField(MagicFilter::root()->text)`).
* Convenience global: `Gruven\PhpBotGram\F` is a top-level **constant** (`const F = new MagicFilter();` — PHP 8.5 added `new` in `const` initializers at the top level). Users import it as `use const Gruven\PhpBotGram\F;` then write `F->text->equals('hi')`. (A plain `use Gruven\PhpBotGram\F;` would import a class symbol, and `F->...` would not be valid PHP — the syntax requires a constant or variable.)

### Layer 2 — `Filters\MagicData`

```php
final class MagicData extends Filter
{
    public function __construct(private readonly MagicFilter $rule) {}

    public function __invoke(TelegramObject $event, mixed ...$kwargs): bool|array
    {
        // The reflection adapter that dispatches filters binds named kwargs by parameter name.
        // MagicData wants the full middleware data dict, so it accepts variadic-named kwargs and
        // reassembles them into a single map (event included for completeness) before resolution.
        $data = ['event' => $event] + $kwargs;
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

Generator output (honest scope):

* One builder class per Telegram event type — **25 user-facing builders** (count matches `aiogram/dispatcher/router.py:38-91`'s 25 update-type observers: `message`, `edited_message`, `channel_post`, `edited_channel_post`, `inline_query`, `chosen_inline_result`, `callback_query`, `shipping_query`, `pre_checkout_query`, `poll`, `poll_answer`, `my_chat_member`, `chat_member`, `chat_join_request`, `message_reaction`, `message_reaction_count`, `chat_boost`, `removed_chat_boost`, `deleted_business_messages`, `business_connection`, `edited_business_message`, `business_message`, `purchased_paid_media`, `managed_bot`, `guest_message`). The `error`/`errors` alias pair dispatches on exception objects, not Telegram payloads, so no `ErrorF` builder is emitted — error handlers use exception-type filters (`ExceptionTypeFilter`, `ExceptionMessageFilter`) instead.
* For nested object fields, the F-DSL ships builders **only for the first-level reachable types** from the event roots: `UserField`, `ChatField`, `MessageEntityField`, `MaybeInaccessibleMessageField`, `BusinessConnectionField` and ≈15 more — *not* the full transitive closure of all 305 schema types. Users who need to drill deeper than the first level drop into the raw `MagicFilter` chain via `$builder->chain()` and continue with `->somethingDeeper->predicate()`. This keeps the generated F-DSL surface around ~50 builder files (event roots + first-level nested types) instead of growing to ~200+.
* `BaseField`, `StringField`, `IntField`, `BoolField`, `RegexField`, `DateTimeField`, `NullableStringField`, `NullableIntField`, `NullableObjectField<T>` are hand-written runtime primitives in `src/Filters/F/` (~12 files).
* Total F-DSL size: ~50 generated builder files + ~12 hand-written runtime primitives.
* If a user reports a repeatedly-needed nested predicate that lives outside the first-level closure, the spec leaves room to extend the codegen to emit a deeper builder for that path on demand.

### Composition with `Command(magic=…)`

`Command(string $name, ?MagicFilter $magic = null)` accepts a `MagicFilter` (raw, not a typed-builder `Filter` instance) for parity with upstream. The `Command` filter walks the magic-filter chain against the parsed `CommandObject`. Users who prefer the typed builder extract the raw chain via the builder's `chain()` accessor (`MessageF::text()->chain()`).

### Opt-out

Users who don't want the magic-filter surface can implement plain `Filter` subclasses or pass closures (`fn (Message $m): bool|array => str_starts_with($m->text ?? '', 'Hi')`).

## Filters in detail

* `Command` — port of `aiogram.filters.command.Command`. PHP forbids parameters after a variadic, so the constructor signature collapses commands into a single array and exposes named options:
  ```php
  public function __construct(
      array $commands = [],                       // list<string|RegexPattern|BotCommand>
      string $prefix = '/',
      bool $ignoreCase = false,
      bool $ignoreMention = false,
      ?MagicFilter $magic = null,
  ) {}

  public static function of(string|RegexPattern|BotCommand ...$values): self;   // variadic-friendly factory
  ```
  Call sites: `new Command(['start', 'help'])` for the array form, `Command::of('start', 'help')` for variadic shorthand. `CommandObject` is a readonly DTO. `RegexPattern` is a thin wrapper over a precompiled PCRE pattern.
* `CommandStart(?bool $deepLink = null, bool $deepLinkEncoded = false, …)`.
* `CallbackData`: abstract class for callback data payloads.
    * Subclasses declare both prefix and separator via a **class-level** attribute: `#[CallbackPrefix(prefix: 'order', sep: ':')]`. The CallbackData constructor only declares the subclass's data fields (readonly promoted properties); `prefix`/`sep` are pure class metadata. `pack()` and `unpack()` read them via `ReflectionClass::getAttributes(CallbackPrefix::class)` cached per `static::class`. The base validates that `$sep` is not contained in `$prefix` lazily on first use — mirroring upstream `__init_subclass__` checks (`filters/callback_data.py:51-65`).
    * `pack(): string` walks constructor-promoted readonly properties via `ReflectionClass`, encodes each via the type-encoding table below, joins with `$sep`, prepends `$prefix`, and validates `strlen($result) <= MAX_CALLBACK_LENGTH` (64 bytes — Telegram limit, `MAX_CALLBACK_LENGTH = 64`). Note: `strlen` measures **bytes** (not characters); since PHP strings are byte sequences and the protocol expects UTF-8 (`aiogram/filters/callback_data.py:101` uses `callback_data.encode()` for the same byte-length check), the byte count matches as long as the input string is UTF-8 — which is the responsibility of the caller. Throws `\LengthException` if oversized.
    * Type-encoding table (matches `filters/callback_data.py:67-82`):
        * `null` → `''` (empty string)
        * `bool` → `'1'` / `'0'`
        * `int`, `float`, `string` → `(string) $value`
        * `\Stringable` (BigDecimal/Fraction equivalents, e.g. `Brick\Math\BigDecimal`, `\GMP`) → `(string) $value`
        * `\UnitEnum` (backed enum) → `$value->value`
        * `\Ramsey\Uuid\UuidInterface` (if installed) → `->getHex()`; otherwise `(string) $value`
        * Any other type → throws `\InvalidArgumentException` (upstream raises `ValueError`)
        * Encoded value containing `$sep` → throws `\InvalidArgumentException`
    * `static unpack(string $value): static` splits on `$sep`, verifies prefix matches, validates field count equals constructor parameter count, decodes nullable-default fields (`''` → property's declared default if nullable, otherwise raises). 1-for-1 port of `filters/callback_data.py:109-139`.
    * `static filter(?MagicFilter $rule = null): CallbackQueryFilter` returns a Filter that unpacks the callback query payload, applies the optional MagicFilter rule, and injects the result as `callback_data` kwarg.
* `StateFilter(State|StatesGroup|string ...$states)`.
* `ChatMemberUpdatedFilter` mirrors upstream's transitions DSL.
* `ExceptionTypeFilter(string ...$classes)` and `ExceptionMessageFilter(string $pattern)` for error handlers (both mirrored from `aiogram/filters/exception.py`).
* `MagicData(MagicFilter $rule)` — see "Magic-filter runtime + F-DSL" section above.
* `Logic\AndFilter`, `OrFilter`, `InvertFilter` — composable via `Filter::all/any/not`.
* `BaseFilter` is registered via `class_alias(Filter::class, 'Gruven\\PhpBotGram\\Filters\\BaseFilter')` so users porting upstream code that imports `aiogram.filters.BaseFilter` (which is re-exported alongside `Filter`) see the same name.

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

* `StatesGroup::bootstrap(): void` is idempotent. It uses `ReflectionClass::getProperties(ReflectionProperty::IS_STATIC | ReflectionProperty::IS_PUBLIC)` to enumerate `State`-typed static properties, instantiates a `State` per property with `new State(state: $propertyName, groupName: null)` (matching upstream `State.__init__(state, group_name=None)` at `fsm/state.py:13`), then immediately calls `$state->setParent(static::class)` to register the owning group (parity with upstream's `set_parent` / `__set_name__` flow at `fsm/state.py:39-48`), and assigns it to the property. After bootstrap, the property is non-null and `$state->group` is set.
* Defense in depth: `StateFilter::__construct(State|StatesGroup|class-string<StatesGroup> ...$states)`, `FSMContext::setState(State|string|null $state)`, and `SceneRegistry::add(class-string<Scene>)` all call `StatesGroup::bootstrapIfNeeded($groupClass)` on every passed group reference. So even if the user forgets the trailing call, the framework's first interaction with the group will boot it. The risk is only in raw property reads (`OrderStates::$waitingProduct`) before any framework call: PHP 8.5 raises `Error: Typed static property OrderStates::$waitingProduct must not be accessed before initialization` at access time — fail-fast, no `null` slips through.
* `bootstrapIfNeeded(class-string<StatesGroup> $group): void` uses a private `array<class-string, true>` flag map to short-circuit re-entry. Walks the inheritance/`Children` chain bottom-up: for each group, the parents listed in `protected const array Children` (and any `StatesGroup` parents in the class hierarchy) are bootstrapped *first*, so a child group's `__full_group_name__` (built from its parent chain) resolves consistently regardless of which group the framework touches first.
* Group nesting: `OrderStates` declares nested groups via a `protected const array CHILDREN = [PaymentStates::class];` constant (UPPER_CASE per PHP convention for class constants); `bootstrap()` recursively resolves them. **Trade-off**: upstream uses visually-nested class declarations (Python supports class nesting; PHP doesn't). The `const CHILDREN` mechanism is a manual registration. An alternative considered — scanning `get_declared_classes()` for `StatesGroup` subclasses declared in the same file — was rejected as too implicit (load order matters, IDE refactoring breaks the link). The repeated `#[ChildGroup(PaymentStates::class)]` class attribute would also work; we keep `const CHILDREN` for its single-call-site clarity. Documented as an intentional deviation.
* `default_state` and `any_state` exposed as `State::defaultState()` and `State::anyState()` static factory methods returning shared singleton `State` instances with `state: null` and `state: '*'` respectively. (Names avoid `State::default()` / `State::any()`: while PHP allows reserved-word method names, the `NameMapper` at the codegen layer forbids them, and using them here would carve out an inconsistent exception.)

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
    public function updateData(array $data = [], mixed ...$kwargs): array;  // $data merges into $kwargs with $data keys winning, matching upstream's kwargs.update(data) at fsm/context.py:38-40
    public function clear(): void;
}
```

### Storage

`BaseStorage` abstract interface (port of `aiogram/fsm/storage/base.py:BaseStorage`):

```php
abstract class BaseStorage
{
    abstract public function setState(StorageKey $key, State|string|null $state = null): void;
    abstract public function getState(StorageKey $key): ?string;
    abstract public function setData(StorageKey $key, array $data): void;
    abstract public function getData(StorageKey $key): array;
    public function getValue(StorageKey $storageKey, string $dictKey, mixed $default = null): mixed;
    public function updateData(StorageKey $key, array $data): array;
    abstract public function close(): void;
}
```

`getValue` and `updateData` are concrete on the base (compose over `getData`/`setData`); subclasses override only when a native single-key/atomic-merge operation is available (Redis HGET, Mongo $set, etc.).

`BaseEventIsolation` abstract: `lock(StorageKey $key): Lock` + `close(): void` — returns a `Lock` object whose `release()` is called by the framework on event completion (closure-block style helper). Upstream uses an `@asynccontextmanager` decorator on `lock()` to make it `async with` compatible; PHP has no equivalent so the port exposes the lock as a plain object with explicit `release()`. The framework wraps handler dispatch in a `try { ... } finally { $lock->release(); }`.

* `MemoryStorage` — in-process map (per Bot instance unless `withBotId(true)` is set on the key builder), used in tests and for single-process bots.
* `RedisStorage::fromUrl(string $url, ?KeyBuilder $keyBuilder = null, int $stateTtl = 0, int $dataTtl = 0)` — built on `amphp/redis`.
* `MongoStorage::fromUrl(string $url, string $database = 'aiogram_fsm', string $collection = 'states_and_data', ?KeyBuilder $keyBuilder = null)` — built on the sync `mongodb/mongodb` driver wrapped via `Amp\async()` so each storage call yields to other Fibers between operations. The two-name split (database name + collection name) mirrors upstream `PyMongoStorage.__init__(db_name='aiogram_fsm', collection_name='states_and_data')` at `aiogram/fsm/storage/pymongo.py:26-27`. **Caveat**: this is a PHP-side compromise. Upstream's current path (`aiogram.fsm.storage.pymongo`) uses `pymongo.AsyncMongoClient` which is **natively async** (non-blocking I/O). PHP's `mongodb/mongodb` is sync-only; `Amp\async()` yields to other Fibers *between* storage calls but each individual Mongo operation still blocks the underlying thread for the duration of the request. For high-FSM-throughput bots this matters — document Redis as the recommended production backend; Mongo is provided for feature parity rather than for throughput parity. This intentionally consolidates upstream's two **async** storages (`aiogram.fsm.storage.mongo` Motor-based; `aiogram.fsm.storage.pymongo` AsyncMongoClient-based, the current recommended path per `aiogram/fsm/storage/pymongo.py:4`) into a single PHP `MongoStorage` built on the sync `mongodb/mongodb` driver. Both upstream storages are async; the PHP port can't match that on a single thread without a native async Mongo driver.
* `BaseEventIsolation`: `SimpleEventIsolation` (in-process via `Amp\Sync\LocalKeyedMutex`), `RedisEventIsolation` (Redis-based `SET NX EX` locking via `amphp/redis`), `DisabledEventIsolation` (no-op).
* `StorageKey` is a readonly DTO. `DefaultKeyBuilder` matches upstream prefix/separator/`with_bot_id`/`with_business_connection_id`/`with_destiny` configuration.

### FSMContextMiddleware

Behaves identically to upstream: enters the isolation lock, materializes `FSMContext`, injects `state` (FSMContext), `raw_state` (string), into handler data.

### Scenes

* `Scene` abstract class extended by user. The class-level `#[SceneState(state: 'order', resetDataOnEnter: false, resetHistoryOnEnter: false, callbackQueryWithoutState: false, attrsResolver: null)]` attribute mirrors upstream's full set of `class OrderScene(Scene, state='order', reset_data_on_enter=..., reset_history_on_enter=..., callback_query_without_state=..., attrs_resolver=...)` class kwargs (`aiogram/fsm/scene.py:321-377`). Omitting the attribute yields a `state: null` scene (matches upstream's default — only one such scene may exist per `SceneRegistry`). The `callbackQueryWithoutState` flag drives the same behavior as upstream: when `true`, the `StateFilter` is **not** installed on the `callback_query` observer, allowing the scene to intercept CB queries before any state is set. The `attrsResolver` accepts an optional callable that customizes attribute resolution on the scene class (port of upstream's `ClassAttrsResolver`); defaults to `null` which uses the default sorted-MRO walker.
* Handlers are declared via per-event marker attributes plus optional `After::*` transitions:
  ```php
  #[SceneState('order')]
  final class OrderScene extends Scene
  {
      #[OnEnter]
      public function start(Message $message, SceneWizard $wizard): void {
          // alias methods return a TelegramMethod bound to the bot via bindBot();
          // emit() (Fiber-friendly) actually fires the API call.
          $message->answer('Choose product')->emit();
      }

      #[OnMessage(filters: [/* MessageF::text()->equals('cancel') */])]
      public function cancel(Message $message, SceneWizard $wizard): void {
          $wizard->exit();
      }

      #[OnCallbackQuery(after: new After(action: SceneAction::Enter, scene: PaymentScene::class))]
      public function gotoPayment(CallbackQuery $cb, SceneWizard $wizard): void { /* … */ }

      #[OnMessage(filters: [/* MessageF::text()->equals('back') */], after: After::back())]
      public function backStep(Message $message, SceneWizard $wizard): void { /* … */ }
  }
  ```
* `Scene` lifecycle hooks: `#[OnEnter]`, `#[OnExit]`, `#[OnLeave]`, `#[OnBack]` (the `leave` hook fires when the scene is left via `goto`, distinct from `exit`; verified against upstream `aiogram/fsm/scene.py:908-927` where `enter/leave/exit/back` are all `ObserverMarker` action methods).
* `Scene` per-event handler attributes — one per Telegram update-type observer (25 total): `#[OnMessage]`, `#[OnEditedMessage]`, `#[OnChannelPost]`, `#[OnEditedChannelPost]`, `#[OnInlineQuery]`, `#[OnChosenInlineResult]`, `#[OnCallbackQuery]`, `#[OnShippingQuery]`, `#[OnPreCheckoutQuery]`, `#[OnPoll]`, `#[OnPollAnswer]`, `#[OnMyChatMember]`, `#[OnChatMember]`, `#[OnChatJoinRequest]`, `#[OnMessageReaction]`, `#[OnMessageReactionCount]`, `#[OnChatBoost]`, `#[OnRemovedChatBoost]`, `#[OnDeletedBusinessMessages]`, `#[OnBusinessConnection]`, `#[OnEditedBusinessMessage]`, `#[OnBusinessMessage]`, `#[OnPurchasedPaidMedia]`, `#[OnManagedBot]`, `#[OnGuestMessage]`. Each attribute accepts `filters: Filter[]` and `after: ?After`. Internally these translate to `ObserverDecorator` instances mirroring `aiogram.fsm.scene.ObserverDecorator` (`scene.py:104-145`). **No `#[OnError]`**: errors are handled outside scenes via the dispatcher's top-level `errors` observer with `ExceptionTypeFilter`/`ExceptionMessageFilter`; scenes never dispatch exception events.
* **`After` action factory** (port of `aiogram.fsm.scene.After`): immutable DTO with `SceneAction $action` and `?class-string<Scene> $scene = null`. Static factories: `After::exit(): After`, `After::back(): After`, `After::goto(class-string<Scene> $scene): After`. When attached via `after:` on a handler attribute, the scene runtime runs the handler then performs the action automatically (exit / pop history / transition into a sibling scene).
* **`Scene::asHandler(mixed ...$handlerKwargs): callable`** and **`Scene::asRouter(?string $name = null): Router`** (port of `scene.py:407-439`) let users mount a scene without going through `SceneRegistry`. Signatures mirror upstream exactly: `as_router(cls, name: str | None = None) -> Router` returns a freshly-constructed `Router` named after the scene (`"Scene 'Foo.Bar'"` if no name given); the caller wires it via `$dp->includeRouter($scene::asRouter())`. `asHandler(...): callable` returns an entry-point handler that enters the scene when invoked, suitable for direct registration: `$dp->message->register(OrderScene::asHandler(), null, new Command(['start']))`.
* `SceneRegistry` mirrors aiogram: `(new SceneRegistry($router, registerOnAdd: true))->add(OrderScene::class, PaymentScene::class)`. Constructor parameter is `Router $router` (matches upstream `SceneRegistry.__init__(router: Router, register_on_add: bool = True)` at `scene.py:751`); `Dispatcher extends Router`, so passing `$dispatcher` is valid. The registry walks each scene's `ObserverDecorator` map and binds them to observers on the router (with the `StateFilter` scope-binding from `scene.py:405`).
* `HistoryManager` ports the snapshot/rollback flow including the `scenes_history` destiny key.
* `ScenesManager` (plural — the **per-update** wizard front-end) is injected as the `scenes` kwarg into every handler reached through the dispatcher. Each update gets its own `ScenesManager` instance (it is **not** a long-lived registry singleton — `SceneRegistry` is the registry). It exposes `enter(string|class-string|null $sceneOrState, bool $_checkActive = true, mixed ...$kwargs): void` and `close(mixed ...$kwargs): void`. 1-for-1 port of upstream `ScenesManager` (`scene.py:678-722`). The leading-underscore `$_checkActive` preserves upstream's "discouraged-but-accepted" naming convention (`scene.py:704`) and is used internally by scene transitions.
* `SceneWizard` (singular — the **per-active-scene** wizard) is accessed as `$this->wizard` inside `Scene` methods (and as the second handler parameter `SceneWizard $wizard`, supplied through the reflection-driven kwarg binding the dispatcher uses everywhere else). It exposes the per-scene operations: `enter()`, `leave()`, `exit()`, `back()`, `retake()`, `goto(string|class-string $target)`, `setData(array $data): void`, `getData(): array`, `updateData(array $data): array`, `getValue(string $key, mixed $default = null): mixed`. 1-for-1 port of upstream `SceneWizard` (`scene.py:551-676`).

## Webhook

`BaseRequestHandler`:

* Abstract methods: `resolveBot(Request $req): Bot`, `verifySecret(string $telegramSecret, Bot $bot): bool`, `close(): void` — where `Request` is `Amp\Http\Server\Request` from `amphp/http-server`.
* `handle(Request $req): Response` runs the dispatcher and either responds synchronously with a Telegram method as the reply body (`multipart/form-data`) or returns an empty JSON `{}` and schedules background processing.

`SimpleRequestHandler` (single Bot, optional `?string $secretToken`).

`TokenBasedRequestHandler` (multi-bot, token in URL `/{bot_token}` — **snake_case** to match upstream `webhook/aiohttp_server.py:298` so existing aiogram webhook configurations rotating between Python and PHP do not 404). Validates that the path template contains `{bot_token}` at registration time.

`Security\IpFilter` matches upstream — built-in Telegram subnets `149.154.160.0/20` and `91.108.4.0/22`.

`Webhook\Server\AmphpServer::run(BaseRequestHandler $handler, string $path, string $host = '0.0.0.0', int $port = 8443, ?array $tlsOptions = null): void` boots an `amphp/http-server` instance routing POST `$path` to the handler.

**`Webhook\Setup::register(\Amp\Http\Server\HttpServer $server, Dispatcher $dispatcher, BaseRequestHandler $handler, string $path, mixed ...$workflowData): void`** wires phpbotgram into an existing `amphp/http-server` application. Like `Server\AmphpServer`, this class file `use`s amphp/http-server types via FQN imports — its autoload triggers the amphp/http-server dependency, but only when the class itself is referenced. Polling-only users never touch it. It adds the POST `$path` route to the server, then registers `Dispatcher::emitStartup(bot: last(bots in workflow), ...$workflowData)` against the server's `onStart` callback and `Dispatcher::emitShutdown(...)` against `onStop` (the "last bot in workflow" is computed via `array_key_last(...)`; PHP arrays don't support upstream's Python `bots[-1]` negative-index syntax). Port of upstream `aiogram/webhook/aiohttp_server.py:22-46 setup_application`. Without this helper, users embedding the framework into an existing amphp/http-server app would have to wire startup/shutdown observers manually — a common foot-gun in upstream `aiogram` setup code.

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

Names follow PHP convention with the `Exception` suffix instead of upstream's `Error` suffix — an intentional, documented deviation from the 1-to-1 mirror policy. PHP code already routes `Error` (a `\Throwable` subtype) for engine-level errors, so naming our framework's recoverable problems `*Error` would clash with reader expectations. Mapping:

| upstream (`Error` suffix) | phpbotgram (`Exception` suffix) |
|---|---|
| `AiogramError` | `PhpBotGramException` |
| `DetailedAiogramError` | `DetailedPhpBotGramException` |
| `TelegramAPIError` | `TelegramApiException` |
| `TelegramNetworkError` | `TelegramNetworkException` |
| `TelegramRetryAfter` | `TelegramRetryAfter` (no suffix in upstream; preserved) |
| `TelegramMigrateToChat` | `TelegramMigrateToChat` (preserved) |
| `TelegramBadRequest`, `TelegramConflictError`, `TelegramForbiddenError`, `TelegramNotFound`, `TelegramServerError`, `TelegramUnauthorizedError` | matching PHP names with `Error` → `Exception` substitution (`TelegramBadRequestException`, `TelegramConflictException`, `TelegramForbiddenException`, `TelegramNotFoundException`, `TelegramServerException`, `TelegramUnauthorizedException`). Note: `TelegramBadRequest`/`TelegramNotFound` in upstream don't end in `Error` but still get the `Exception` suffix for uniformity. |
| `RestartingTelegram` | `RestartingTelegram` (preserved — doesn't end in `Error`) |
| `TelegramEntityTooLarge` | `TelegramEntityTooLarge` (preserved — doesn't end in `Error`) |
| `ClientDecodeError` | `ClientDecodeException` |
| `DataNotDictLikeError` | `DataNotDictLikeException` |
| `UnsupportedKeywordArgument` | `UnsupportedKeywordArgumentException` |
| `CallbackAnswerException` | `CallbackAnswerException` (already PHP-style) |
| `SceneException` | `SceneException` (already PHP-style) |
| `UpdateTypeLookupError` | `UpdateTypeLookupException` |

Per-class payload fields (beyond the inherited `method` + `message`):

* `TelegramApiException`: `method: TelegramMethod`, `message: string` (base class fields).
* `TelegramRetryAfter`: adds `retryAfter: int` (Telegram-supplied retry hint in seconds, per `aiogram/exceptions.py:89-102`).
* `TelegramMigrateToChat`: adds `migrateToChatId: int` (target supergroup id, per `aiogram/exceptions.py:112-123`).
* `ClientDecodeException`: adds `original: \Throwable` (the upstream decode exception) and `data: mixed` (the raw payload that failed to decode).
* `UnsupportedKeywordArgumentException`: carries the offending kwarg name set as a property for tooling diagnostics.
* All other concrete subclasses (`TelegramBadRequest`, `TelegramNotFound`, `TelegramConflictException`, `TelegramUnauthorizedException`, `TelegramForbiddenException`, `TelegramServerException`, `RestartingTelegram`, `TelegramEntityTooLargeException`) carry only `method` + `message`.

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

* `MockedSession` ports upstream behavior: an in-memory deque of canned responses + a deque of recorded outgoing methods. Exposes `addResult(Response $r): Response` and `getRequest(): TelegramMethod`. **Intentional deviation from upstream**: upstream's `MockedSession.make_request` declares `timeout: int | None = UNSET_PARSE_MODE` (`tests/mocked_bot.py:33`) — a bug where the default value is a `Default('parse_mode')` sentinel mistakenly used as an int. The PHP port uses the type-correct `?int $timeout = null` instead. (The PHP port's renames `Default`→`BotDefault` and `UNSET`→`Unspecified` would have made the bug a hard type error anyway.)
* `MockedBot extends Bot` injects `MockedSession`, exposes `addResultFor(string $methodClass, bool $ok, mixed $result = null, ?string $description = null, int $errorCode = 200, ...): Response` and `getRequest(): TelegramMethod`. Pre-stubs `$bot->me()` to return a fixed `User` (id derived from the test token `42:TEST` so `bot.id === 42`, `username = 'tbot'`, etc.) — matches `tests/mocked_bot.py:63-70`. Without that stub, `Dispatcher::_polling` (which calls `$bot->me()` before entering the loop) cannot run against `MockedBot`.
* `tests/bootstrap.php` configures Revolt's `EventLoop` driver explicitly so async tests use a deterministic loop. Per-test isolation is achieved by **resetting the driver** in `tearDown`: `Revolt\EventLoop::setDriver(new \Revolt\EventLoop\Driver\StreamSelectDriver())`. This is the destructive but reliable approach — Revolt v1 does not expose a public callback-enumeration API, so attempting to "snapshot and cancel" individual callbacks is fragile. A fresh driver per test guarantees no cross-test leakage. Any test that holds a closure over an `EventLoop` callback ID across cases is unsupported (none of our planned tests do this).
* Async tests use `\Amp\async(...)->await()` to drive Fibers; helper `runAsync(\Closure $body): mixed` (in-house, ~40 LOC under `tests/Support/RunAsync.php`) drives Revolt's event loop inside a test method via `Future::await()`. The helper is implemented as a PHPUnit trait `RunAsyncTrait` that hooks into `setUp`/`tearDown` for the driver reset. `amphp/phpunit-util` is incompatible with our PHPUnit 13 baseline, so we don't depend on it.
* Mocking strategy:
    * `MockedSession`/`MockedBot` for HTTP-layer assertions (the upstream pattern).
    * `Dispatcher::silentCallRequest` is mocked via a thin recording proxy: `tests/Support/RecordingDispatcher.php` extends `Dispatcher` and overrides `silentCallRequest()` to push calls onto a public `array $silentCalls` for inspection. This replaces upstream's `unittest.mock.patch("aiogram.dispatcher.dispatcher.Dispatcher.silent_call_request", new_callable=AsyncMock)` idiom, which doesn't translate cleanly to PHP static-method mocking; making `silentCallRequest` a public **instance** method (deviation noted earlier) is what enables this.
    * PHPUnit's `MockBuilder` covers `BaseStorage`, `Filter`, `BaseMiddleware`, etc. where appropriate.
    * Signal-emulation in polling-loop tests is done by directly resolving the dispatcher's shared `$stopSignal` deferred — bypassing `EventLoop::onSignal` since PHPUnit cannot raise OS signals.
* Parameterized cases use PHPUnit data providers.
* **pytest fixture → PHPUnit fixture mapping**: upstream `conftest.py` exposes `bot`, `dispatcher`, `memory_storage`, `redis_storage`, `pymongo_storage`, `lock_isolation`, `disabled_isolation`, `storage_key` as pytest fixtures with auto-injection by parameter name. PHPUnit 13 has no parameter-name fixture injection; the port uses **one trait per fixture group** under `tests/Support/`:
    * `MakesMockedBot` — declares `protected MockedBot $bot;` (plain property, assigned in the trait's `#[Before]` hook).
    * `MakesDispatcher` — declares `protected Dispatcher $dispatcher;`, with `#[Before]` emitting `startup` and `#[After]` emitting `shutdown`.
    * `MakesMemoryStorage` — declares `protected MemoryStorage $memoryStorage;` with `#[After]` closing.
    * `MakesRedisStorageWhenEnv` — checks `PHPBOTGRAM_REDIS_DSN` env var in `#[Before]`, otherwise marks the test skipped via `$this->markTestSkipped()`; declares `protected RedisStorage $redisStorage;` plus `#[After]` that flushes and closes.
    * `MakesPyMongoStorageWhenEnv` — same shape for Mongo.
    * `MakesLockIsolation` / `MakesDisabledIsolation` — provide isolation fixtures.
    * `MakesStorageKey` — declares `protected StorageKey $storageKey;` keyed on `bot_id=42, chat_id=-42, user_id=42` (matching upstream constants).
    * Test classes opt in to whichever traits they need via `use MakesMockedBot, MakesDispatcher, MakesMemoryStorage;`. PHPUnit 13's trait `setUp` chaining (via the `#[Before]` attribute or trait-merged `setUp()`) keeps composition explicit.

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
| **0. Bootstrap** | Composer deps locked (amphp/amp ^3, amphp/http-client ^5, revolt/event-loop ^1, amphp/byte-stream ^2 + ext-mbstring; require-dev: amphp/redis ^2, amphp/http-server ^3, mongodb/mongodb ^2, phpstan ^2.1, twig/twig ^3.10), namespace skeleton, CI scaffolding (GitHub Actions PHP 8.5 + Redis/Mongo services), MockedBot/MockedSession harness with `me()` pre-stub, base `TelegramObject`, `TelegramMethod<T>`, `BotDefault`, `Unspecified`, `BotContextController` (incl. `withBot()` deep-clone), `TelegramApiServer`, exception tree | `phpunit` runs on empty suite; CI green |
| **1. Foundation** | `Bot` (skeleton, no API methods yet), `BaseSession`, `AmphpSession`, `InputFile` (Buffered/Fs/Url), `Serializer` (dump/load with recursive bot binding), `RequestMiddlewareManager`. Phase 1 hand-writes a minimum `SendMessage` + `Message` + `Bot::sendMessage()` for the Phase 1 smoke test; these are deleted and regenerated in Phase 2. | Manual roundtrip test: `sendMessage` hand-coded against a test bot |
| **2. Codegen** | Copy `.butcher` schema, build `tools/generator/` (incl. all six pipeline stages + the F-DSL templates emitting `Filters/F/*` builders), regenerate all Enums, Types (with `aliases.yml`-derived shortcut methods baked into class bodies), Methods, the Bot facade, the F-DSL builder catalog | Generator emits valid PHP; `phpstan` level 9 passes on `src/`; `phpunit` smoke test instantiates 50 random types end-to-end (serialize → deserialize); generator round-trips with no `git diff` |
| **3. Dispatcher** | `Router`, `Dispatcher`, `TelegramEventObserver` (incl. `filter(...)` global filter), `EventObserver`, `HandlerObject`, `FilterObject`, `CallableObject` (reflection-driven kwarg binding), `Flags`, polling loop (per-bot semaphore, signal handling, startup/shutdown bot injection), webhook response contract (`silentCallRequest`, 55s deferred), `ErrorsMiddleware`, `UserContextMiddleware` | Echo bot example runs against a mock session; `tests/Dispatcher/*` port complete |
| **4. Filters & magic-filter runtime** | `Filter` base, `Command`/`CommandStart`/`CommandObject`, `CallbackData` (full type-encoding table + 64-byte limit), `StateFilter`, `Logic` combinators, `ChatMemberUpdatedFilter`, `ExceptionTypeFilter`, **`Utils\MagicFilter\MagicFilter` runtime port** (~800 LOC), **`Filters\MagicData`**, wire-up of generated `Filters\F\*` builders to the runtime | Port `tests/test_filters/*` + `tests/test_utils/test_magic_filter.py` |
| **5. FSM** | `State`/`StatesGroup` bootstrap (explicit `bootstrap()` + framework-side `bootstrapIfNeeded` defense), `StorageKey`/`DefaultKeyBuilder`, `FSMContext`, `MemoryStorage`, `RedisStorage`, `MongoStorage`, isolations (`Simple`/`Disabled`/`Redis`), `FSMContextMiddleware`, `Scene`/`SceneRegistry`/`HistoryManager`/`ScenesManager`/`SceneWizard` (attributes: `#[OnEnter]`/`#[OnExit]`/`#[OnLeave]`/`#[OnBack]`/`#[OnMessage]`/...) | Port `tests/test_fsm/*` (Redis/Mongo skipped without env DSN) |
| **6. Webhook** | `BaseRequestHandler`, `SimpleRequestHandler`, `TokenBasedRequestHandler`, `IpFilter`, `AmphpServer`, multipart-form response builder, `handleInBackground` defaults | Port `tests/test_webhook/*`, smoke test via amphp/http-server in-process |
| **7. Utils** | TextDecoration (Html/Markdown with UTF-16LE surrogate accounting via ext-mbstring), DeepLinking, Keyboard builders, MediaGroup, ChatAction, CallbackAnswer, Backoff, WebApp/AuthWidget, Token | Port `tests/test_utils/*` |
| **8. Tests + examples** | Port remaining upstream tests, all 12+ example scripts, README quickstart, `gruven/phpbotgram-i18n` skeleton (lives in a sibling repo / monorepo subdir; not bundled into core) | ≥90 % coverage; CI green across full matrix |
| **9. Polish** | Documentation, sample webhook deployment configs (nginx + amphp/http-server example), README, CHANGELOG | Tag `v0.1.0`, public preview |

## Open questions / risks

* **Property hooks vs. readonly trade-off**: PHP 8.4 property hooks can mimic Pydantic computed fields, but they conflict with `readonly`. The initial port sticks to `readonly` everywhere; computed accessors (e.g. `Update::eventType`) become methods (`getEventType(): string`) returning lazily-memoized values stored in a `private array $computed` map.
* **Multipart streaming over HTTP/2**: amphp/http-client supports HTTP/2, but a few Telegram local Bot API forks reportedly mishandle HTTP/2 multipart bodies. The session defaults to HTTP/1.1 for outbound traffic and exposes an `useHttp2: bool` option.
* **Mongo async story**: `mongodb/mongodb` is sync; wrapping with `Amp\async()` yields to other Fibers between calls but each call still blocks the underlying thread for the duration of the Mongo request. Upstream's `pymongo.AsyncMongoClient` is natively non-blocking; PHP cannot match that on a single thread. The initial port accepts this trade-off and recommends Redis for high-throughput FSM workloads; a deferred follow-up may introduce a native amphp Mongo driver if maintenance demands it.
* **`feedUpdate` re-mount validator semantics**: `withBot()` is a structural deep-clone of an already-validated `Update`. Upstream re-runs Pydantic validators on every nested field via the `model_dump → model_validate` JSON roundtrip. Tests that exercise validator behavior on `feed_update` will not exercise it identically in PHP. Mitigation: if a parity-bug is discovered, switch the re-mount path to `Serializer::load(Update::class, $update->jsonSerialize(), bot: $bot)` (full re-validation, slower).
* **PHP 8.5 cadence**: PHP 8.5 is the release branch active at design time. If a contributor needs PHP 8.4, the package's lowest-supported version can be relaxed without API changes — the generator output uses no PHP 8.5-exclusive syntax outside the `|>` operator (used sparingly, replaceable by method chains).
* **Generator maintenance burden**: keeping the codegen in PHP means PHP-side contributors can self-serve schema updates. Upstream's Python butcher remains the canonical reference for any schema patch we don't yet handle; the `scripts/sync-schema.sh` step pulls the latest schema.json plus patches and re-runs the generator.
* **MagicFilter port scope (largest hand-authored subsystem)**: porting `magic_filter` to PHP plus aiogram's `.as_()` extension is the biggest single non-codegen workload in the framework. The runtime DSL is ~1000 LOC in Python; PHP needs equivalent coverage of `__get`/`__call`/`cast`/operations + reflection-friendly resolution + sealed value semantics through chains. The earlier "~800 LOC" target is the optimistic case; treat 1200-1500 LOC as the realistic bound and budget Phase 4 accordingly. If the port slips, this is the most likely culprit.
* **PHPUnit 13 baseline (verified)**: `phpunit/phpunit ^13.1` is the GA major as of the design date (`13.1.8` on packagist, confirmed). `amphp/phpunit-util v3` still pins to PHPUnit 9 — that's the compatibility gap we mitigate via the in-house Fiber helper, not a release-cadence problem.

## Acceptance criteria

* `composer install` on a fresh checkout pulls only the documented dependencies.
* `php examples/echo_bot.php` runs a bot end-to-end via long polling against `api.telegram.org`.
* `php examples/echo_bot_webhook.php` boots an amphp/http-server instance and handles incoming updates.
* `vendor/bin/phpunit` passes on every CI matrix entry without external services; full matrix with Redis + Mongo also passes locally with the env DSNs set.
* `tools/generator/bin/generate.php --schema .butcher/schema/schema.json --out src/` is **deterministic**: emitted file order, in-file declaration order, and whitespace are stable across runs. A second consecutive run produces a working tree with no `git diff`. Determinism is enforced by (a) sorting schema iteration alphabetically by entity name, (b) using a single fixed `php-cs-fixer` rule set on emitted files as a final pass, (c) avoiding any timestamps/hashes in generated code, and (d) running Twig with `cache: false` so no compiled-template artifacts leak into the output tree. The generator itself lives in `require-dev` (`twig/twig`), so `composer install --no-dev` consumers cannot regenerate — this is intentional, since generated code is committed and end users do not need codegen.
* `vendor/bin/phpstan analyze` returns clean at level 9 across `src/` and `tests/`.
* `vendor/bin/php-cs-fixer fix --dry-run` returns no diff.
