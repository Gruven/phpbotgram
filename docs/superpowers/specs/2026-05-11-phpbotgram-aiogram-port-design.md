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
| Tests | `phpunit/phpunit` ^13.1 + in-house Fiber helper | `amphp/phpunit-util` is pinned to PHPUnit 9 and is incompatible with our PHPUnit 13 baseline; we ship a tiny `RunAsync` test helper (≈40 LOC) that drives Revolt's event loop inside test methods |
| Static analysis | `phpstan/phpstan` ^2.1 level 9 with generics via docblocks | `TelegramMethod<TReturn>` carried in `@template`/`@extends` |
| Style | `friendsofphp/php-cs-fixer` (already configured) | Existing `.php-cs-fixer.dist.php` retained |

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
│   ├── I18n (optional, behind feature flag)
│   └── MagicFilter\Internals        # primitives used by generated F-builders
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

* `Default` is a final readonly class with a `string $name` property — exactly aiogram's behavior.
* `Unset` is a readonly singleton (`Unset::instance()`) used as the sentinel for "argument not provided" cases. The serializer strips fields whose value is `Unset::instance()` before validation/encoding.

Polling loop:

* `Dispatcher::startPolling(Bot ...$bots, int $pollingTimeout = 10, bool $handleAsTasks = true, ?BackoffConfig $backoffConfig = null, ?array $allowedUpdates = null, bool $handleSignals = true, bool $closeBotSession = true, ?int $tasksConcurrencyLimit = null, mixed ...$kwargs): void`.
* Each bot runs its own polling task via `Amp\async()`. A `Amp\Sync\LocalSemaphore` enforces `$tasksConcurrencyLimit`.
* `EventLoop::onSignal(SIGINT, …)` + `EventLoop::onSignal(SIGTERM, …)` handle graceful shutdown when `$handleSignals` is true.
* `Dispatcher::runPolling(...)` is the public sync wrapper that boots the event loop via `Amp\trapSignal` then awaits the future returned by `startPolling`.

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

PHP CLI built with `symfony/console` + `twig/twig`.

* `bin/generate.php --schema .butcher/schema/schema.json --patches .butcher --out src/`
* Pipeline:
  1. `SchemaLoader` parses `schema.json` + applies per-entity patches.
  2. `TypeResolver` maps Telegram primitive type strings to PHP types:
     * `Integer` → `int`
     * `String` → `string`
     * `Boolean` → `bool`
     * `Float` → `float`
     * `True` → `true` (literal type)
     * `Array of X` → `array` (PHP) annotated with PHPStan `list<X>`
     * `X or Y` → `X|Y` union
     * Date/time-ish strings handled by `DateTime` subclass on the `Message.date`-style fields (per aiogram custom `DateTime` field).
  3. `NameMapper` converts snake_case → camelCase, escapes PHP reserved words (`from` → `fromUser`, `class` → `className`, etc.).
  4. `UnionDetector` identifies sealed unions (e.g. `BackgroundFill`) and emits both:
     * abstract base class `BackgroundFill` with discriminator field `type`,
     * concrete subclasses (`BackgroundFillSolid`, …),
     * type alias `BackgroundFillUnion` (PHP has no real type alias — emitted as a docblock `@phpstan-type` plus a generated final class with `public const string Type` constants for runtime dispatch).
  5. `ShortcutDetector` reads `aliases.yml` per type to inject convenience methods (`Message::answer()`, `Message::reply()`, …). Hand-authored shortcuts (those aiogram embeds in `types/message.py`) are kept in a parallel `src/Types/Shortcuts/MessageShortcuts.php` trait that the generator `use`s in the rendered class.
  6. `Renderer` emits PHP files into the target directories, formatted to match php-cs-fixer rules.

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

The generator emits `src/Bot.php` (~6000 lines, matching upstream `bot.py`). Each generated method:

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
* Owns one `TelegramEventObserver` per Bot API event type (`message`, `editedMessage`, `channelPost`, `editedChannelPost`, `inlineQuery`, `chosenInlineResult`, `callbackQuery`, `shippingQuery`, `preCheckoutQuery`, `poll`, `pollAnswer`, `myChatMember`, `chatMember`, `chatJoinRequest`, `messageReaction`, `messageReactionCount`, `chatBoost`, `removedChatBoost`, `deletedBusinessMessages`, `businessConnection`, `editedBusinessMessage`, `businessMessage`, `purchasedPaidMedia`, `managedBot`, `guestMessage`, `error`). Plus `startup` / `shutdown` `EventObserver` instances for lifecycle hooks.
* `includeRouter(Router $r)` and `includeRouters(Router ...)`.
* `resolveUsedUpdateTypes(?array $skip = null): array<string>`.
* `propagateEvent(string $type, TelegramObject $event, mixed ...$kwargs): mixed`.

`Dispatcher extends Router`:

* `feedUpdate(Bot $bot, Update $update, mixed ...$kwargs): mixed`.
* `feedRawUpdate(Bot $bot, array $update, mixed ...$kwargs): mixed`.
* `feedWebhookUpdate(Bot $bot, Update|array $update, float $timeout = 55.0, mixed ...$kwargs): ?TelegramMethod`.
* `startPolling(...)` / `runPolling(...)` / `stopPolling()`.

`TelegramEventObserver`:

* `register(callable $handler, Filter|callable ...$filters, ?array $flags = null): callable`.
* `__invoke(Filter|callable ...$filters, ?array $flags = null): callable` — decorator-style factory matching aiogram's `@router.message(...)`. In PHP this returns a closure used as `$router->message(F::all(...))(fn(Message $m) => …)`. Attribute-based registration (`#[OnMessage]`) is offered as an optional convenience layer.
* `outerMiddleware` and `middleware` collections matching upstream `MiddlewareManager`.
* `trigger(TelegramObject $event, mixed ...$kwargs): mixed`.

`Filter`:

* `abstract public function __invoke(mixed ...$args): bool|array;`
* `updateHandlerFlags(array &$flags): void` for filters like `Command` that contribute flags.
* `__invert__()` analogue: `not(): Filter` returning an `InvertFilter`.
* Static helpers: `Filter::all(...): AndFilter`, `Filter::any(...): OrFilter`, `Filter::not(Filter): InvertFilter`.

`BaseMiddleware`:

```php
abstract class BaseMiddleware
{
    /** @param callable(TelegramObject, array<string, mixed>): mixed $handler */
    abstract public function __invoke(callable $handler, TelegramObject $event, array $data): mixed;
}
```

## F-DSL (typed builders, generated)

The `F` namespace ships per-event typed builders generated from the same schema as types. For each Telegram event type the generator emits a builder class with one static factory per public field:

```php
namespace Gruven\PhpBotGram\Filters\F;

final class MessageF
{
    public static function text(): StringField { /* ... */ }
    public static function caption(): NullableStringField { /* ... */ }
    public static function chat(): ChatField { /* ... */ }
    public static function fromUser(): NullableUserField { /* ... */ }
    public static function date(): DateTimeField { /* ... */ }
    // ... one per field
}
```

Field-builder classes provide typed combinators:

```php
final class StringField extends BaseField
{
    public function equals(string $value): Filter { /* ... */ }
    public function in(array $values): Filter { /* ... */ }
    public function startsWith(string $value): Filter { /* ... */ }
    public function endsWith(string $value): Filter { /* ... */ }
    public function contains(string $value): Filter { /* ... */ }
    public function regex(string $pattern): Filter { /* ... */ }
    public function length(): IntField { /* ... */ }
}

final class IntField extends BaseField
{
    public function equals(int $value): Filter { /* ... */ }
    public function in(array $values): Filter { /* ... */ }
    public function gt(int $value): Filter { /* ... */ }
    public function lt(int $value): Filter { /* ... */ }
    public function between(int $min, int $max): Filter { /* ... */ }
}
```

Each Field builder records the accessor path (e.g. `fromUser.id`) plus the comparison operation. The runtime resolver walks the event tree using compiled getters.

Field-builder terminal methods (`equals`, `startsWith`, `in`, `gt`, …) always return a `Filter`. Logical combinators are surfaced on `Filter` instances, not on field builders, so they compose only after a terminal call:

```php
MessageF::text()->startsWith('Hi')                              // Filter
    ->and(MessageF::fromUser()->id()->equals(123))              // Filter
    ->or(MessageF::caption()->contains('hello'));               // Filter
```

`Filter::all(Filter ...$filters)`, `Filter::any(Filter ...$filters)`, `Filter::not(Filter $f)` provide static-constructor equivalents.

Generator output spans ~30 event-typed builder files (one per Telegram event type) — bundled into `src/Filters/F/` alongside `BaseField`, `StringField`, `IntField`, `BoolField`, `NullableStringField`, `ObjectField<T>`, and field-builder mirrors for nested types (`UserField`, `ChatField`, …). Total generated F-DSL size is comparable to the type catalog itself.

Users opting out of F-DSL can implement plain `Filter` subclasses or pass closures (`fn(Message $m) => str_starts_with($m->text, 'Hi')`).

## Filters in detail

* `Command(string|RegexPattern|BotCommand ...$values, string $prefix = '/', bool $ignoreCase = false, bool $ignoreMention = false, ?Filter $magic = null)` — port of `aiogram.filters.command.Command`. `CommandObject` is a readonly DTO. `RegexPattern` is a thin wrapper over a precompiled PCRE pattern.
* `CommandStart(?bool $deepLink = null, bool $deepLinkEncoded = false, …)`.
* `CallbackData`: abstract class for callback data payloads. Subclasses declare `#[CallbackPrefix('order')]` attribute on the class. `pack(): string` walks readonly constructor properties via reflection; `static unpack(string $value): static` parses the colon-separated tail. `static filter(?Filter $rule = null): CallbackQueryFilter`.
* `StateFilter(State|StatesGroup|string ...$states)`.
* `ChatMemberUpdatedFilter` mirrors upstream's transitions DSL.
* `ExceptionTypeFilter(string ...$classes)` for error handlers.
* `Logic\AndFilter`, `OrFilter`, `InvertFilter` — composable via `Filter::all/any/not`.

## FSM and Scenes

### State / StatesGroup

State definition uses static `State` properties initialized via a Reflection-driven bootstrap:

```php
final class OrderStates extends StatesGroup
{
    public static State $waitingProduct;
    public static State $waitingAddress;
    public static State $confirming;
}
```

* `StatesGroup` base class has a `static bootstrap(): void` method called lazily via `__init_static__` (PHP doesn't run static initializers automatically — bootstrap is triggered the first time `StatesGroup::all()`, `StatesGroup::states()`, or any `OrderStates::$waitingProduct` access happens). Bootstrap is idempotent and uses `ReflectionClass` to enumerate static `State`-typed properties, assigning each a `State` instance with its property name plus group hierarchy.
* Group nesting: `OrderStates` can include nested groups via a `protected const array Children = [PaymentStates::class];` constant; bootstrap recursively resolves children.
* `default_state` and `any_state` constants exposed as `State::default()` and `State::any()`.

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
* `MongoStorage::fromUrl(string $url, string $collection = 'aiogram_fsm', ?KeyBuilder $keyBuilder = null)` — built on `mongodb/mongodb`. Async operations wrapped with `Amp\async()`.
* `BaseEventIsolation`: `SimpleEventIsolation` (in-process via `Amp\Sync\LocalKeyedMutex`), `RedisEventIsolation` (Redis-based `SET NX EX` locking), `DisabledEventIsolation` (no-op).
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
* `I18n` (optional): port of aiogram's gettext-based i18n using `symfony/translation` as the message catalog backend. Behind a feature flag (separate optional namespace) so we avoid the Symfony dep in the core install path.

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

* `MockedSession` ports upstream behavior: an in-memory deque of canned responses + a deque of recorded outgoing methods.
* `MockedBot extends Bot` injects `MockedSession`, exposes `addResultFor(method, ok, result, …)` and `getRequest(): TelegramMethod`.
* `tests/bootstrap.php` configures Revolt's `EventLoop::queue` for predictable test scheduling.
* Async tests use `\Amp\async(...)->await()` to drive Fibers; helper `runAsync(\Closure $body): mixed` (in-house, ~40 LOC under `tests/Support/RunAsync.php`) drives Revolt's event loop inside a test method. `amphp/phpunit-util` is incompatible with our PHPUnit 13 baseline, so we don't depend on it.
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
| **0. Bootstrap** | Composer deps locked, namespace skeleton, CI scaffolding, MockedBot/MockedSession harness, base `TelegramObject`, `TelegramMethod<T>`, `Default`, `Unset`, `BotContextController`, `TelegramApiServer`, exception tree | `phpunit` runs on empty suite; CI green |
| **1. Foundation** | `Bot` (skeleton, no API methods yet), `BaseSession`, `AmphpSession`, `InputFile` (Buffered/Fs/Url), `Serializer` (dump/load), `RequestMiddlewareManager` | Manual roundtrip test: `sendMessage` hand-coded against a test bot |
| **2. Codegen** | Copy `.butcher` schema, build `tools/generator/`, regenerate all Enums, Types, Methods, plus the Bot facade | Generator emits valid PHP; `phpstan` passes; `phpunit` smoke test instantiates 50 random types |
| **3. Dispatcher** | `Router`, `Dispatcher`, `TelegramEventObserver`, `EventObserver`, `HandlerObject`, `FilterObject`, `CallableObject`, `Flags`, polling loop, signal handling, `ErrorsMiddleware`, `UserContextMiddleware` | Echo bot example runs against a mock session |
| **4. Filters** | `Filter` base, `Command`/`CommandStart`/`CommandObject`, `CallbackData`, `StateFilter`, `Logic` combinators, `ChatMemberUpdatedFilter`, `ExceptionTypeFilter`, F-DSL codegen | Port `tests/test_filters/*` |
| **5. FSM** | `State`/`StatesGroup` bootstrap, `StorageKey`/`DefaultKeyBuilder`, `FSMContext`, `MemoryStorage`, `RedisStorage`, `MongoStorage`, isolations, `FSMContextMiddleware`, `Scene`/`SceneRegistry`/`HistoryManager` | Port `tests/test_fsm/*` (Redis/Mongo skipped without env) |
| **6. Webhook** | `BaseRequestHandler`, `SimpleRequestHandler`, `TokenBasedRequestHandler`, `IpFilter`, `AmphpServer`, multipart-form response builder | Port `tests/test_webhook/*`, smoke test via amphp/http-server in-process |
| **7. Utils** | TextDecoration, DeepLinking, Keyboard builders, MediaGroup, ChatAction, CallbackAnswer, Backoff, WebApp/AuthWidget, Token, I18n (optional, gated) | Port `tests/test_utils/*` |
| **8. Tests + examples** | Port remaining upstream tests, all 12+ example scripts, README quickstart | ≥90 % coverage; CI green across full matrix |
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
