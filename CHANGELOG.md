# Changelog

All notable changes to phpbotgram are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] — 2026-06-21

### Fixed

- `Serializer::load()` now hydrates `CallbackQuery::$message` through the generated `MaybeInaccessibleMessageUnion` resolver instead of trying to instantiate the abstract `MaybeInaccessibleMessage` base class. Payloads with `date = 0` resolve to `InaccessibleMessage`; normal payloads resolve to `Message`.
- Scene handlers and lifecycle action methods now filter dispatcher workflow kwargs by method signature. Strict scene methods such as `onEnter(Message $event)` no longer need a `mixed ...$kwargs` tail just because middleware or workflow data injected unrelated keys.
- Active scene routers now get priority over parent catch-all handlers while `raw_state` is set, so broad root `message` handlers do not consume messages intended for the active scene.
- `DefaultKeyBuilder` now appends non-default destiny values even when `withDestiny` is `false`, keeping ordinary default FSM keys compact while allowing scene history (`scenes_history`) to work with Redis and Mongo storage defaults.

### Changed

- Router dispatch keeps the existing parent-local-first order for normal routers and inactive FSM state, but scene routers created by `Scene::asRouter()` are tried before parent-local handlers when an FSM state is active. Root handlers that must run during scenes should use explicit filters or middleware rather than relying on catch-all order.

## [0.1.0] — Initial release

First public release. Ports aiogram 3.29.0 / Telegram Bot API 10.1 to PHP 8.5 with full feature parity for the core framework. Behaviour divergences from upstream are documented inline at the call site (`# Divergence:` comments) and in `docs/superpowers/specs/`.

### Added

#### Bot API 10.1 schema sync

- Generated Telegram Bot API surface synced from aiogram 3.29.0 / Bot API 10.1.
- Rich-message support:
  - `InputRichMessage`, `RichMessage`, `RichBlock*`, `RichText*`, `Link`, `InputMediaLink`, and the generated `RichBlockUnion` / `RichTextUnion` helpers.
  - `Bot::sendRichMessage`, `Bot::sendRichMessageDraft`, `Message::answerRich`, and `Message::replyRich`.
  - `EditMessageText::$richMessage` for editing rich messages without breaking the existing positional `$text` shortcut.
- Chat-join request query methods: `Bot::answerChatJoinRequestQuery` and `Bot::sendChatJoinRequestWebApp`.
- Serializer support for PHPDoc-backed generated list parameters such as `list<RichBlock>` and nested rich-block table structures.
- Serializer support for the recursive rich text wire union: a plain string, a rich-text object, or a list mixing strings and rich-text segments.
- README badges now track aiogram 3.29.0, Bot API 10.1, and the current passing test count.
- Narrative docs for sending, replying with, streaming, and reading rich messages.

#### Bot client and HTTP transport

- `Bot` facade that builds typed API method DTOs for every Telegram Bot API method (codegen output, regenerate via `make regenerate`).
- `BaseSession` abstract with `prepareValue`, `checkResponse`, `buildResponse`, `deserializeResult`, and the polymorphic `union:` ReturnsType discriminator.
- `AmphpSession` production HTTP adapter built on `amphp/http-client ^5` (form-urlencoded bodies; multipart deferred).
- Typed exception hierarchy: `TelegramApiException`, `TelegramBadRequestException`, `TelegramConflictException`, `TelegramForbiddenException`, `TelegramNetworkException`, `TelegramNotFoundException`, `TelegramRetryAfter`, `TelegramServerException`, `TelegramUnauthorizedException`, `TelegramEntityTooLarge`, `TelegramMigrateToChat`, `RestartingTelegram`.
- `DefaultBotProperties` (parseMode, link-preview aggregation, `ArrayAccess` read-only surface).
- `TelegramApiServer` production / test / fromBase factories for the local-server mode.
- `Serializer::dump()` / `Serializer::load()` for snake_case wire conversion with `WireNames` per-class overrides and recursive TelegramObject / union resolution.
- `BotShortcuts` trait providing `getId`, `me`, `context`, `downloadFile`, `download`, plus the FiberLocal current-bot binding.

#### Dispatcher, routers, middleware

- `Dispatcher` extends `Router` and owns the polling loop (`startPolling`/`runPolling`), graceful shutdown via signals, and the 25-update-type observer map.
- `Router` cascade with parent-locked `includeRouter` / `includeRouters` and post-include immutability.
- `TelegramEventObserver` for every update type, with:
  - registration via Closure or any callable (Filter subclass, MagicFilterAsFilter, anonymous invokables — all wrapped via `Closure::fromCallable`).
  - dual decorator surface (`$observer($cb, filters:)` eager vs. `$observer(filters:)` factory).
  - global filter chain plus per-handler filter pipeline.
  - outer / inner middleware stacks with proper composition order.
- `BaseMiddleware` abstract for both outer and inner roles.
- `Flags` system with `#[Flag]` attribute and imperative `FlagDecorator::attach` for closures, with merge semantics ("manual wins").

#### Filters and the F-DSL

- `Command` filter with prefix/case-sensitivity/bot-username gating and `CommandObject` parsed result.
- `CommandStart` for `/start` deep links.
- `CallbackData` abstract with `pack` / `unpack` / `filter()` static builder, `#[CallbackPrefix]` attribute, and `decodeComplex()` for nested scalars / enums (int- and string-backed).
- `StateFilter` and `State::__invoke` for FSM state predicates.
- `ChatMemberUpdatedFilter` for `chat_member` / `my_chat_member` member transition filtering.
- `ExceptionTypeFilter`, `ExceptionMessageFilter` for error handlers.
- `MagicData` filter bridging the F-DSL against the dispatch data dict.
- `Filter::all()`, `Filter::any()`, `Filter::invertOf()` combinators returning `AndFilter` / `OrFilter` / `InvertFilter`.
- `F` constant + `MagicFilter` chain DSL with comparator ops (`equals`, `notEquals`, `in_`, `contains`, `startsWith`, `endsWith`, `regexp`, `gt`/`gte`/`lt`/`lte`, `between`, `as_`).
- Typed `F-DSL` field wrappers: `IntField`, `StringField`, `BoolField`, `DateTimeField`, `NullableIntField`, `NullableStringField`, `NullableObjectField`, `RegexField`, `BaseField`.

#### FSM

- `FsmContext` with state/value accessors (`getState`, `setState`, `getValue`, `setData`, `updateData`, `getData`, `clear`).
- `FsmStrategy` (`UserInChat`, `Chat`, `GlobalUser`, `UserInTopic`, `ChatTopic`) plus `StorageKey` resolver covering chat / user / message thread isolation.
- Storage backends:
  - `MemoryStorage` (in-process default).
  - `RedisStorage` (integration tests gated by `PHPBOTGRAM_TEST_REDIS_DSN`).
  - `MongoStorage` (integration tests gated by `PHPBOTGRAM_TEST_MONGO_DSN`).
- `StatesGroup` and `State` (explicit; no metaclass auto-discovery).
- Scenes:
  - `Scene` base with reflection-driven `sceneConfig()` extraction.
  - `SceneWizard` (enter / exit / leave / retake / goto / back).
  - `SceneRegistry` with eager `add([Scene::class])` wiring.
  - `ScenesManager` injected as a handler kwarg.
  - `#[SceneState]`, `#[OnMessage]`, `#[OnCallbackQuery]`, `#[OnChatJoinRequest]`, … attribute markers.
  - `After` lifecycle directives (Enter, Exit, Back, etc.).

#### Webhook

- `AmphpServer::run()` wrapping `amphp/http-server ^3` with shutdown hooks tied to the dispatcher lifecycle.
- `SimpleRequestHandler` (single bot) and `TokenBasedRequestHandler` (multi-tenant routing on the URL path token).
- `IpFilter` middleware enforcing Telegram's CIDR ranges.
- `Setup::register()` to splice the bot lifecycle into an existing amphp/http-server instance.
- Constant-time secret-token validation via `hash_equals`.

#### Utils

- `TextDecoration` with `HtmlDecoration` and `MarkdownDecoration` (Markdown V2) strategies — entity-aware escaping, full V2 special-char coverage, expandable-blockquote support.
- `DeepLinking` for `/start` payload encode/decode with WeakMap-cached bot binding.
- Keyboard builders: `InlineKeyboardBuilder`, `ReplyKeyboardBuilder`, shared `KeyboardBuilder` base.
- `MediaGroupBuilder` for grouped media uploads.
- `ChatActionSender` + `ChatActionMiddleware` with `DeferredCancellation`-managed `raceDelay` ticking.
- `CallbackAnswer` DTO + `CallbackAnswerMiddleware` (pre/post modes).
- `WebApp` signature verification (`WebAppSignature`, `WebAppInitData`, `WebAppUser`, `WebAppChat`) using `sodium_crypto_sign_verify_detached` for Ed25519 and `hash_equals` for HMAC compare.
- `AuthWidget` for the Telegram Login Widget data validation.
- `Payload`, `Link`, `Token` parsing utilities.
- `Backoff` + `BackoffConfig` for exponential-with-jitter retry pacing used by the polling loop.
- `DeepLinkType` enum classifying `/start` payload kinds.

#### Tooling

- `tools/generator/` produces `src/Types/Generated/`, `src/Methods/Generated/`, `src/Enums/Generated/`, plus the `Bot.php` facade from the upstream Telegram API spec.
- `scripts/coverage-gate.php` enforces per-module coverage floors (Bot ≥80%, Session ≥75%, Dispatcher/Router/Filters/FSM ≥90%).
- Make targets and composer scripts: `test`, `stan`, `lint`, `fix`, `regenerate`, `coverage`, `coverage-gate`, `docs-api` (script and target names match).
- 12 runnable examples under `examples/` mirroring upstream aiogram's example surface.
- Deployment templates under `deploy/` (nginx reverse proxy, systemd unit, Docker compose).
- API documentation pipeline: `phpdocumentor/shim` composer dev-dep drops the official phpDocumentor v3 phar into `vendor/bin/phpdoc` on `composer install`; `composer docs-api` (or `make docs-api`) renders the site into `build/docs/api/`. GitHub Actions (`.github/workflows/docs.yml`) publishes the site to GitHub Pages on every push to `master`.

### Added — narrative documentation

- Diataxis-structured narrative site under `docs/guide/en/` (47 committed pages: 1 top-level landing + 6 tutorial + 22 how-to + 17 concept + 1 reference stub) plus 2 build-time copies of CHANGELOG.md and CONTRIBUTING.md → 49 rendered pages.
- `phpdoc.dist.xml.tpl` template (envsubst → `phpdoc.dist.xml`) rendering narrative + API into a single phpDocumentor v3 site under `build/docs/api/`.
- `.phpdoc/template/components/header.html.twig` override injecting a navbar language+version switcher driven by `versions.json` and `languages.json` served from the gh-pages branch root.
- Seven post-build CI gates in `scripts/build-docs.sh`:
  - `check-docs-build-log.php` greps phpdoc stderr for unresolved refs / orphan docs / missing-title / missing-alt-text warnings.
  - `check-docs-links.php` verifies every sentinel-URL (`https://api.phpbotgram.local/...`) points at a real API page.
  - `rewrite-api-links.php` HTML-aware DOM rewrite of sentinel URLs to `classes/...`, with HTML5-doctype preservation and a 50% size-shrink sanity guard.
  - `check-internal-links.php` walks rendered HTML for non-sentinel internal links and validates them against `<base href>`, with macOS `/var`-vs-`/private/var` realpath canonicalisation.
  - `lint-docs.php` runs `php -l` on every fenced ` ```php ` block and bans inline raw HTML in narrative prose.
  - `check-docs-examples.php` verifies every `examples/X.php` link resolves to an existing file.
  - `markdownlint-cli2@0.22.1` for prose style.
- `.github/workflows/docs.yml` migrated from Pages "workflow mode" to "branch mode" via `peaceiris/actions-gh-pages@v4`; publishes master pushes to `/en/dev/`.
- New `.github/workflows/docs-release.yml` publishes tag pushes to `/en/<tag>/` + `/en/latest/` + updates `versions.json` with a semver-shape validation gate at job entry.
- `update-versions-json.php` atomic CLI with `stable=auto` semver-aware backport handling.
- `copy-root-docs.php` mirrors project-root `CHANGELOG.md` and `CONTRIBUTING.md` into `docs/guide/en/` pre-build (gitignored copies with AUTO-GENERATED banner; mtime preserved).
- `CONTRIBUTING.md` at project root with "Fenced-block conventions" documenting the ` ```php ` (lint-gated) vs ` ```php-fragment ` (lint-skipped) distinction.
- `.markdownlint.jsonc` config compatible with the copied CHANGELOG/CONTRIBUTING (MD024 siblings_only for Keep-a-Changelog shape).
- `composer.json` constraint for `phpdocumentor/shim` tightened from `^3` to `~3.10.0`.
- `composer docs-api` and `make docs-api` both delegate to `bash scripts/build-docs.sh`.

### Quality bars

- 2168 PHPUnit tests with 6929 assertions (9 env-gated skips). Real Redis / MongoDB integration tests gated on `PHPBOTGRAM_TEST_REDIS_DSN` / `PHPBOTGRAM_TEST_MONGO_DSN` env vars.
- PHPStan level 9, clean.
- `php-cs-fixer` enforced.
- Coverage gate passes at the documented per-module floors.

### Known divergences from aiogram 3.29.0

- No async/await keywords — fiber-based runtime via amphp v3 / Revolt makes the dispatch path synchronous from the caller's perspective.
- Scenes are explicit (no metaclass auto-discovery) — register via `SceneRegistry::add([Scene::class])` because PHP has no metaclasses.
- No `model_dump` / `model_validate` — `Serializer::dump` / `Serializer::load` cover the same surface with PHP reflection.
- `Filter::__invoke` signature is `(object $event, mixed ...$kwargs)` (variadic) to round-trip dispatcher kwargs without case translation.
- Handler kwargs use literal name matching (no snake_case ↔ camelCase conversion); filter return keys, `workflowData` keys, and handler parameter names must agree.

[0.1.0]: https://github.com/Gruven/phpbotgram/releases/tag/v0.1.0
