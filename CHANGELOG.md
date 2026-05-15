# Changelog

All notable changes to phpbotgram are documented in this file. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] — Initial release

First public release. Ports aiogram 3.28 to PHP 8.5 with full feature
parity for the core framework. Behaviour divergences from upstream are
documented inline at the call site (`# Divergence:` comments) and in
`docs/superpowers/specs/`.

### Added

#### Bot client and HTTP transport

- `Bot` facade that builds typed API method DTOs for every Telegram Bot
  API method (codegen output, regenerate via `make regenerate`).
- `BaseSession` abstract with `prepareValue`, `checkResponse`,
  `buildResponse`, `deserializeResult`, and the polymorphic `union:`
  ReturnsType discriminator.
- `AmphpSession` production HTTP adapter built on `amphp/http-client ^5`
  (form-urlencoded bodies; multipart deferred).
- Typed exception hierarchy: `TelegramApiException`,
  `TelegramBadRequestException`, `TelegramConflictException`,
  `TelegramForbiddenException`, `TelegramNetworkException`,
  `TelegramNotFoundException`, `TelegramRetryAfter`,
  `TelegramServerException`, `TelegramUnauthorizedException`,
  `TelegramEntityTooLarge`, `TelegramMigrateToChat`, `RestartingTelegram`.
- `DefaultBotProperties` (parseMode, link-preview aggregation,
  `ArrayAccess` read-only surface).
- `TelegramApiServer` production / test / fromBase factories for the
  local-server mode.
- `Serializer::dump()` / `Serializer::load()` for snake_case wire
  conversion with `WireNames` per-class overrides and recursive
  TelegramObject / union resolution.
- `BotShortcuts` trait providing `getId`, `me`, `context`, `downloadFile`,
  `download`, plus the FiberLocal current-bot binding.

#### Dispatcher, routers, middleware

- `Dispatcher` extends `Router` and owns the polling loop
  (`startPolling`/`runPolling`), graceful shutdown via signals, and the
  25-update-type observer map.
- `Router` cascade with parent-locked `includeRouter` / `includeRouters`
  and post-include immutability.
- `TelegramEventObserver` for every update type, with:
  - registration via Closure or any callable (Filter subclass,
    MagicFilterAsFilter, anonymous invokables — all wrapped via
    `Closure::fromCallable`).
  - dual decorator surface (`$observer($cb, filters:)` eager vs.
    `$observer(filters:)` factory).
  - global filter chain plus per-handler filter pipeline.
  - outer / inner middleware stacks with proper composition order.
- `BaseMiddleware` abstract for both outer and inner roles.
- `Flags` system with `#[Flag]` attribute and imperative
  `FlagDecorator::attach` for closures, with merge semantics ("manual
  wins").

#### Filters and the F-DSL

- `Command` filter with prefix/case-sensitivity/bot-username gating and
  `CommandObject` parsed result.
- `CommandStart` for `/start` deep links.
- `CallbackData` abstract with `pack` / `unpack` / `filter()` static
  builder, `#[CallbackPrefix]` attribute, and `decodeComplex()` for nested
  scalars / enums (int- and string-backed).
- `StateFilter` and `State::__invoke` for FSM state predicates.
- `ChatMemberUpdatedFilter` for `chat_member` / `my_chat_member` member
  transition filtering.
- `ExceptionTypeFilter`, `ExceptionMessageFilter` for error handlers.
- `MagicData` filter bridging the F-DSL against the dispatch data dict.
- `Filter::all()`, `Filter::any()`, `Filter::invertOf()` combinators
  returning `AndFilter` / `OrFilter` / `InvertFilter`.
- `F` constant + `MagicFilter` chain DSL with comparator ops (`equals`,
  `notEquals`, `in_`, `contains`, `startsWith`, `endsWith`, `regexp`,
  `gt`/`gte`/`lt`/`lte`, `between`, `as_`).
- Typed `F-DSL` field wrappers: `IntField`, `StringField`, `BoolField`,
  `DateTimeField`, `NullableIntField`, `NullableStringField`,
  `NullableObjectField`, `RegexField`, `BaseField`.

#### FSM

- `FsmContext` with state/value accessors (`getState`, `setState`,
  `getValue`, `setData`, `updateData`, `getData`, `clear`).
- `FsmStrategy` (`UserInChat`, `Chat`, `GlobalUser`, `UserInTopic`,
  `ChatTopic`) plus `StorageKey` resolver covering chat / user /
  message thread isolation.
- Storage backends:
  - `MemoryStorage` (in-process default).
  - `RedisStorage` (env-gated by `PHPBOTGRAM_REDIS_DSN`).
  - `MongoStorage` (env-gated by `PHPBOTGRAM_MONGO_DSN`).
- `StatesGroup` and `State` (explicit; no metaclass auto-discovery).
- Scenes:
  - `Scene` base with reflection-driven `sceneConfig()` extraction.
  - `SceneWizard` (enter / exit / leave / retake / goto / back).
  - `SceneRegistry` with eager `add([Scene::class])` wiring.
  - `ScenesManager` injected as a handler kwarg.
  - `#[SceneState]`, `#[OnMessage]`, `#[OnCallbackQuery]`,
    `#[OnChatJoinRequest]`, … attribute markers.
  - `After` lifecycle directives (Enter, Exit, Back, etc.).

#### Webhook

- `AmphpServer::run()` wrapping `amphp/http-server ^3` with shutdown
  hooks tied to the dispatcher lifecycle.
- `SimpleRequestHandler` (single bot) and `TokenBasedRequestHandler`
  (multi-tenant routing on the URL path token).
- `IpFilter` middleware enforcing Telegram's CIDR ranges.
- `Setup::register()` to splice the bot lifecycle into an existing
  amphp/http-server instance.
- Constant-time secret-token validation via `hash_equals`.

#### Utils

- `TextDecoration` with `HtmlDecoration` and `MarkdownDecoration`
  (Markdown V2) strategies — entity-aware escaping, full V2 special-char
  coverage, expandable-blockquote support.
- `DeepLinking` for `/start` payload encode/decode with WeakMap-cached
  bot binding.
- Keyboard builders: `InlineKeyboardBuilder`, `ReplyKeyboardBuilder`,
  shared `KeyboardBuilder` base.
- `MediaGroupBuilder` for grouped media uploads.
- `ChatActionSender` + `ChatActionMiddleware` with
  `DeferredCancellation`-managed `raceDelay` ticking.
- `CallbackAnswer` DTO + `CallbackAnswerMiddleware` (pre/post modes).
- `WebApp` signature verification (`WebAppSignature`, `WebAppInitData`,
  `WebAppUser`, `WebAppChat`) using `sodium_crypto_sign_verify_detached`
  for Ed25519 and `hash_equals` for HMAC compare.
- `AuthWidget` for the Telegram Login Widget data validation.
- `Payload`, `Link`, `Token` parsing utilities.
- `Backoff` + `BackoffConfig` for exponential-with-jitter retry pacing
  used by the polling loop.
- `DeepLinkType` enum classifying `/start` payload kinds.

#### Tooling

- `tools/generator/` produces `src/Types/Generated/`,
  `src/Methods/Generated/`, `src/Enums/Generated/`, plus the `Bot.php`
  facade from the upstream Telegram API spec.
- `scripts/coverage-gate.php` enforces per-module coverage floors
  (Bot ≥80%, Session ≥75%, Dispatcher/Router/Filters/FSM ≥90%).
- Make targets: `test`, `stan`, `lint`, `fix`, `regenerate`, `coverage`,
  `coverage-gate`, `docs-api`.
- 12 runnable examples under `examples/` mirroring upstream aiogram's
  example surface.
- Deployment templates under `deploy/` (nginx reverse proxy, systemd
  unit, Docker compose).
- API documentation site generated via phpDocumentor v3
  (`make docs-api` outputs to `build/docs/api/`).

### Quality bars

- 2109 PHPUnit tests with 6599 assertions (9 env-gated skips). Real
  Redis / MongoDB integration tests gated on
  `PHPBOTGRAM_REDIS_DSN` / `PHPBOTGRAM_MONGO_DSN` env vars.
- PHPStan level 9, clean.
- `php-cs-fixer` enforced.
- Coverage gate passes at the documented per-module floors.

### Known divergences from aiogram 3.28

- No async/await keywords — fiber-based runtime via amphp v3 / Revolt
  makes the dispatch path synchronous from the caller's perspective.
- Scenes are explicit (no metaclass auto-discovery) — register via
  `SceneRegistry::add([Scene::class])` because PHP has no metaclasses.
- No `model_dump` / `model_validate` — `Serializer::dump` /
  `Serializer::load` cover the same surface with PHP reflection.
- `Filter::__invoke` signature is `(object $event, mixed ...$kwargs)`
  (variadic) to round-trip dispatcher kwargs without case translation.
- Handler kwargs use literal name matching (no snake_case ↔ camelCase
  conversion); filter return keys, `workflowData` keys, and handler
  parameter names must agree.

[0.1.0]: https://github.com/Gruven/phpbotgram/releases/tag/v0.1.0
