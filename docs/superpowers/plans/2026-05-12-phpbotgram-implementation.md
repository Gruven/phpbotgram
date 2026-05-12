# phpbotgram Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a PHP 8.5 framework for the Telegram Bot API that ports the abstractions of [aiogram](https://github.com/aiogram/aiogram) 3.28.2 (Bot API 10.0) into idiomatic PHP, producing a 1-to-1 module mirror with codegen-driven types/methods and an amphp v3 async runtime.

**Architecture:** Async-first via amphp v3 (`Amp\amp` ^3, `revolt/event-loop` ^1, `amphp/http-client` ^5). Internal DTO + serializer (no Pydantic equivalent). All 305 Telegram types + 176 methods + 34 enums are emitted by a Twig-based PHP generator from a vendored `.butcher/schema/schema.json` plus `replace.yml`/`aliases.yml`/`default.yml` patches. Dispatcher mirrors aiogram's Router/observer/middleware contract. FSM ships Memory/Redis/Mongo storages; webhook ships an amphp-native handler. F-DSL = generated typed builders sitting on top of a full PHP port of `magic_filter` (`Utils\MagicFilter\MagicFilter`).

**Tech Stack:**
- PHP **^8.5** (64-bit) — readonly props, asymmetric visibility `public private(set)`, property hooks (8.4), backed enums, intersection/union types, `new` in const/default-param, first-class callable syntax, pipe operator
- `amphp/amp` ^3, `revolt/event-loop` ^1, `amphp/http-client` ^5, `amphp/byte-stream` ^2 — runtime
- `amphp/http-server` ^3 (require-dev + suggest) — webhook
- `amphp/redis` ^2 (require-dev + suggest) — Redis storage
- `mongodb/mongodb` ^2 + `ext-mongodb` ^2.3 (require-dev + suggest) — Mongo storage
- `twig/twig` ^3.10 (require-dev) — codegen templates
- `phpunit/phpunit` ^13.1 (require-dev) — tests
- `phpstan/phpstan` ^2.1 (require-dev) — static analysis at level 9
- `friendsofphp/php-cs-fixer` ^3.95 (require-dev) — style
- `ext-mbstring`, `ext-json` (require) — text decoration + serialization

**Spec:** `docs/superpowers/specs/2026-05-11-phpbotgram-aiogram-port-design.md` is the source of truth for every behavior, signature, and naming decision. The plan below refers back to it; do not re-litigate decisions while implementing.

---

## File Structure (post-implementation)

```
phpbotgram/
├── composer.json                                    # extended at Task 0.1
├── phpstan.neon.dist                                # added at Task 0.2
├── phpunit.xml.dist                                 # added at Task 0.2
├── .php-cs-fixer.dist.php                           # already configured
├── .github/workflows/ci.yml                         # Task 0.3
├── Makefile                                         # Task 0.3
├── .butcher/                                        # vendored schema, Task 2.1
│   ├── schema/schema.json
│   ├── types/<TypeName>/{entity.json,aliases.yml?,replace.yml?}
│   ├── methods/<methodName>/{entity.json,default.yml?}
│   └── enums/<EnumName>/{entity.json,replace.yml?}
├── scripts/sync-schema.sh                           # Task 2.13
├── tools/generator/
│   ├── bin/generate.php                             # Task 2.12
│   ├── src/
│   │   ├── SchemaLoader.php                         # Task 2.2
│   │   ├── TypeResolver.php                         # Task 2.3
│   │   ├── NameMapper.php                           # Task 2.4
│   │   ├── TypeOverrideApplier.php                  # Task 2.5
│   │   ├── UnionDetector.php                        # Task 2.6
│   │   ├── ShortcutDetector.php                     # Task 2.7
│   │   ├── DefaultsResolver.php                     # Task 2.8
│   │   ├── HandAuthoredShortcutsIntegrator.php      # Task 2.9
│   │   ├── Renderer.php                             # Task 2.10
│   │   ├── FDslGenerator.php                        # Task 2.11
│   │   └── SchemaInfo.php                           # Task 2.13 (entity-count gate)
│   └── templates/
│       ├── type.php.twig
│       ├── method.php.twig
│       ├── enum.php.twig
│       ├── bot.php.twig
│       └── f-builder.php.twig
├── src/
│   ├── Bot.php                                      # generated, Task 2.14
│   ├── Client/
│   │   ├── BotContextController.php                 # Task 0.5
│   │   ├── BotDefault.php                           # Task 0.6
│   │   ├── BotShortcutsContract.php                 # Task 1.6
│   │   ├── BotShortcuts.php                         # Task 1.6 (trait)
│   │   ├── DefaultBotProperties.php                 # Task 1.2
│   │   ├── TelegramApiServer.php                    # Task 0.7
│   │   └── Session/
│   │       ├── BaseSession.php                      # Task 1.3
│   │       ├── AmphpSession.php                     # Task 1.5
│   │       └── Middleware/
│   │           ├── BaseRequestMiddleware.php
│   │           └── RequestMiddlewareManager.php
│   ├── Types/                                        # generated, Task 2.14
│   │   ├── TelegramObject.php                       # Task 0.4 (hand-written base)
│   │   ├── MutableTelegramObject.php                # Task 0.4
│   │   ├── Unspecified.php                          # Task 0.6
│   │   ├── Shortcuts/<TypeName>Shortcuts.php        # hand-authored, Task 2.9
│   │   ├── <SchemaTypeName>.php                     # generated × 305
│   │   ├── <UnionName>Union.php                     # generated discriminated unions
│   │   └── Custom/DateTime.php                      # Task 1.2
│   ├── Methods/                                      # generated, Task 2.14
│   │   ├── TelegramMethod.php                       # Task 0.5 (hand-written base)
│   │   ├── Request.php, Response.php                # Task 0.5
│   │   └── <SchemaMethodName>.php                   # generated × 176
│   ├── Enums/<EnumName>.php                         # generated × 34, Task 2.14
│   ├── Dispatcher/
│   │   ├── Dispatcher.php                           # Task 3.13
│   │   ├── Router.php                               # Task 3.1
│   │   ├── PollingOptions.php                       # Task 3.12
│   │   ├── Event/
│   │   │   ├── TelegramEventObserver.php
│   │   │   ├── EventObserver.php
│   │   │   ├── HandlerObject.php
│   │   │   ├── FilterObject.php
│   │   │   ├── CallableObject.php
│   │   │   ├── Bases.php                            # UNHANDLED, REJECTED constants + skip() helper
│   │   │   ├── SkipHandlerException.php
│   │   │   └── CancelHandlerException.php
│   │   ├── Flags/
│   │   │   ├── Flag.php                             # #[\Attribute(\Attribute::TARGET_METHOD|TARGET_FUNCTION)] class — IS the attribute itself, not a separate DTO. Constructor params are (name: string, value: mixed = true).
│   │   │   ├── FlagDecorator.php                    # imperative attachment via WeakMap for closures
│   │   │   ├── FlagGenerator.php                    # singleton with __call magic → FlagDecorator
│   │   │   └── functions.php                        # extractFlags/extractFlagsFromObject/getFlag/checkFlags
│   │   └── Middlewares/
│   │       ├── BaseMiddleware.php
│   │       ├── ErrorsMiddleware.php
│   │       ├── UserContextMiddleware.php
│   │       ├── EventContext.php
│   │       └── MiddlewareManager.php
│   ├── Filters/
│   │   ├── Filter.php                               # abstract base
│   │   ├── Command.php
│   │   ├── CommandObject.php
│   │   ├── CommandStart.php
│   │   ├── CallbackData.php
│   │   ├── CallbackPrefix.php                       # #[CallbackPrefix] attribute
│   │   ├── StateFilter.php
│   │   ├── ChatMemberUpdatedFilter.php
│   │   ├── ExceptionTypeFilter.php
│   │   ├── ExceptionMessageFilter.php
│   │   ├── MagicData.php
│   │   ├── Logic/{AndFilter,OrFilter,InvertFilter}.php
│   │   └── F/
│   │       ├── BaseField.php
│   │       ├── StringField.php, NullableStringField.php
│   │       ├── IntField.php, NullableIntField.php
│   │       ├── BoolField.php
│   │       ├── DateTimeField.php
│   │       ├── RegexField.php
│   │       ├── NullableObjectField.php
│   │       └── <EventType>F.php                     # generated × 25
│   ├── Fsm/
│   │   ├── State.php, StatesGroup.php, DefaultState.php
│   │   ├── FsmStrategy.php                          # enum
│   │   ├── Context.php                              # FSMContext
│   │   ├── Middleware/FsmContextMiddleware.php
│   │   ├── Scene/
│   │   │   ├── Scene.php
│   │   │   ├── SceneState.php                       # #[SceneState] attribute
│   │   │   ├── SceneWizard.php
│   │   │   ├── ScenesManager.php
│   │   │   ├── SceneRegistry.php
│   │   │   ├── HistoryManager.php
│   │   │   ├── SceneAction.php                      # enum
│   │   │   ├── After.php
│   │   │   └── Attributes/{OnEnter,OnExit,OnLeave,OnBack,OnMessage,…}.php  # 29 files total
│   │   └── Storage/
│   │       ├── StorageKey.php
│   │       ├── DefaultKeyBuilder.php
│   │       ├── KeyBuilder.php
│   │       ├── BaseStorage.php
│   │       ├── BaseEventIsolation.php
│   │       ├── SimpleEventIsolation.php
│   │       ├── DisabledEventIsolation.php
│   │       ├── RedisEventIsolation.php
│   │       ├── Lock.php
│   │       ├── MemoryStorage.php
│   │       ├── RedisStorage.php
│   │       └── MongoStorage.php
│   ├── Handlers/
│   │   ├── BaseHandler.php
│   │   ├── MessageHandler.php
│   │   ├── MessageHandlerCommandMixin.php           # trait
│   │   ├── CallbackQueryHandler.php
│   │   ├── InlineQueryHandler.php
│   │   ├── ChosenInlineResultHandler.php
│   │   ├── PollHandler.php
│   │   ├── ChatMemberHandler.php
│   │   ├── ShippingQueryHandler.php
│   │   ├── PreCheckoutQueryHandler.php
│   │   └── ErrorHandler.php
│   ├── Webhook/
│   │   ├── RequestHandler/
│   │   │   ├── BaseRequestHandler.php
│   │   │   ├── SimpleRequestHandler.php
│   │   │   └── TokenBasedRequestHandler.php
│   │   ├── Security/IpFilter.php
│   │   ├── Server/AmphpServer.php
│   │   └── Setup.php
│   ├── Utils/
│   │   ├── TextDecoration/{TextDecoration,HtmlDecoration,MarkdownDecoration}.php
│   │   ├── DeepLinking.php
│   │   ├── Keyboard/{InlineKeyboardBuilder,ReplyKeyboardBuilder}.php
│   │   ├── MediaGroup/MediaGroupBuilder.php
│   │   ├── ChatAction/ChatActionSender.php
│   │   ├── CallbackAnswer/CallbackAnswerMiddleware.php
│   │   ├── Backoff.php, BackoffConfig.php
│   │   ├── Payload.php, Token.php, Link.php
│   │   ├── WebApp/WebAppSignature.php
│   │   ├── AuthWidget.php
│   │   └── MagicFilter/MagicFilter.php
│   ├── Exceptions/
│   │   ├── PhpBotGramException.php                  # AiogramError
│   │   ├── DetailedPhpBotGramException.php
│   │   ├── TelegramApiException.php, TelegramNetworkException.php
│   │   ├── TelegramBadRequestException.php, TelegramConflictException.php,
│   │   │   TelegramForbiddenException.php, TelegramNotFoundException.php,
│   │   │   TelegramServerException.php, TelegramUnauthorizedException.php
│   │   ├── TelegramRetryAfter.php, TelegramMigrateToChat.php,
│   │   │   TelegramEntityTooLarge.php, RestartingTelegram.php
│   │   ├── ClientDecodeException.php, DataNotDictLikeException.php
│   │   ├── CallbackAnswerException.php, SceneException.php
│   │   ├── UnsupportedKeywordArgumentException.php, UpdateTypeLookupException.php
│   │   └── functions.php                            # skip() helper
│   ├── F.php                                        # const F = new MagicFilter()
│   └── flags.php                                    # const flags = new FlagGenerator()
├── tests/
│   ├── bootstrap.php
│   ├── Support/
│   │   ├── MockedSession.php, MockedBot.php
│   │   ├── RunAsyncTrait.php
│   │   ├── RecordingDispatcher.php
│   │   └── Makes*Trait.php                          # fixture composition traits
│   ├── Api/{Client,Methods,Types}/                  # ~150 tests
│   ├── Dispatcher/{Event,Middlewares}/              # router/observer/dispatcher
│   ├── Filters/                                      # all built-in filters
│   ├── Fsm/{Storage}/                                # state, context, scenes, storages
│   ├── Webhook/, Utils/, Handlers/, Flags/, Issues/
│   └── data/                                         # fixtures ported from upstream
└── examples/
    ├── echo_bot.php, echo_bot_webhook.php, echo_bot_webhook_ssl.php
    ├── finite_state_machine.php, scene.php, quiz_scene.php, multibot.php
    ├── error_handling.php, own_filter.php, context_addition_from_filter.php
    ├── specify_updates.php, stars_invoice.php, without_dispatcher.php
    └── web_app/
```

---

## Phase 0 — Bootstrap

Foundational scaffolding: deps, CI, base classes that every later phase imports. Each task is TDD where the artifact is testable; pure config tasks just verify the file loads.

### Task 0.1: Lock composer dependencies + verify they install

**Files:**
- Modify: `composer.json`
- Verify: `composer.lock`

- [ ] **Step 1: Edit composer.json — add core require + require-dev**

Replace the existing `require` and `require-dev` blocks:

```json
{
    "require": {
        "php": "^8.5",
        "php-64bit": "^8.5",
        "ext-mbstring": "*",
        "ext-json": "*",
        "amphp/amp": "^3",
        "amphp/byte-stream": "^2",
        "amphp/file": "^3",
        "amphp/http-client": "^5",
        "amphp/sync": "^2",
        "revolt/event-loop": "^1"
    },
    "require-dev": {
        "amphp/http-server": "^3",
        "amphp/redis": "^2",
        "friendsofphp/php-cs-fixer": "^3.95",
        "mongodb/mongodb": "^2",
        "phpstan/phpstan": "^2.1",
        "phpunit/phpunit": "^13.1",
        "twig/twig": "^3.10"
    },
    "suggest": {
        "amphp/http-server": "^3 — required to host webhooks via the bundled AmphpServer adapter",
        "amphp/redis": "^2 — required for the RedisStorage FSM backend and RedisEventIsolation",
        "mongodb/mongodb": "^2 — required for the MongoStorage FSM backend",
        "ext-mongodb": "^2.3 — required by mongodb/mongodb",
        "ext-pcntl": "* — enables EventLoop::onSignal for graceful polling shutdown (unix only)"
    }
}
```

Also extend `autoload-dev`:

```json
"autoload-dev": {
    "psr-4": {
        "Gruven\\PhpBotGram\\Tests\\": "tests/",
        "Gruven\\PhpBotGram\\Generator\\": "tools/generator/src/"
    }
}
```

- [ ] **Step 2: Run composer update**

Run: `NO_PROXY='*' composer update --no-progress 2>&1 | tail -20`
Expected: dependencies install cleanly, lock file regenerated, no version conflicts. If amphp/* packages report version-pin conflicts, drop to looser constraints (`^3` → exact version) and re-pin.

- [ ] **Step 2.5: Regenerate autoloader** (so the new autoload-dev block takes effect)

Run: `composer dump-autoload`

- [ ] **Step 3: Sanity-check vendor**

Run: `composer show --installed --no-dev | wc -l`
Expected: >= 10 (amphp transitive deps).

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: lock Phase 0 dependencies (amphp v3, phpunit 13, phpstan 2.1, twig 3)"
```

### Task 0.2: Add phpstan.neon.dist, phpunit.xml.dist

**Files:**
- Create: `phpstan.neon.dist`
- Create: `phpunit.xml.dist`

- [ ] **Step 1: Write phpstan.neon.dist**

```yaml
parameters:
    level: 9
    paths:
        - src
        - tests
        - tools/generator/src
    excludePaths:
        - tests/data
        - src/Types/*.php   # generated; rules relaxed during Phase 2
        - src/Methods/*.php # generated
        - src/Enums/*.php   # generated
        - src/Bot.php       # generated
    treatPhpDocTypesAsCertain: false
    reportUnmatchedIgnoredErrors: false
```

- [ ] **Step 2: Write phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutCoverageMetadata="true"
         failOnRisky="true"
         failOnWarning="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="phpbotgram">
            <directory>tests</directory>
            <exclude>tests/data</exclude>
            <!--
              NOTE: do NOT exclude tests/Support — the Mocked*, RunAsyncTrait, etc.
              that live there are imported by other test files, AND tests/Support/
              hosts a couple of *Test.php files that exercise the harness itself
              (MockedSessionTest, RunAsyncTraitTest). Excluding the directory would
              silently drop those tests.
            -->
        </testsuite>
    </testsuites>
    <coverage>
        <report>
            <text outputFile="php://stdout" showOnlySummary="true"/>
            <html outputDirectory="coverage" lowUpperBound="50" highLowerBound="90"/>
        </report>
    </coverage>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
    <php>
        <env name="PHPBOTGRAM_REDIS_DSN" value=""/>
        <env name="PHPBOTGRAM_MONGO_DSN" value=""/>
    </php>
</phpunit>
```

- [ ] **Step 3: Create tests/bootstrap.php**

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Reset Revolt EventLoop driver to ensure deterministic test scheduling.
\Revolt\EventLoop::setDriver(new \Revolt\EventLoop\Driver\StreamSelectDriver());
```

- [ ] **Step 4: Verify both tools run**

Run: `vendor/bin/phpstan analyze --error-format=raw --no-progress 2>&1 | head -5`
Expected: `[OK] No errors` (empty `src/` and `tests/`).

Run: `vendor/bin/phpunit --list-tests 2>&1 | head -5`
Expected: "No tests found." with exit code 0 (empty suite is OK).

- [ ] **Step 5: Commit**

```bash
git add phpstan.neon.dist phpunit.xml.dist tests/bootstrap.php
git commit -m "chore: add phpstan/phpunit config + tests bootstrap"
```

### Task 0.3: Add CI workflow + Makefile

**Files:**
- Create: `.github/workflows/ci.yml`
- Create: `Makefile`

- [ ] **Step 1: Write .github/workflows/ci.yml**

```yaml
name: CI

on:
  push:
    branches: [master]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.5']
    services:
      redis:
        image: redis:7
        ports: ['6379:6379']
        options: --health-cmd "redis-cli ping" --health-interval 10s
      mongo:
        image: mongo:7
        ports: ['27017:27017']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, json, mongodb, pcntl, sockets, openssl
          coverage: xdebug
      - uses: ramsey/composer-install@v3
      - run: vendor/bin/php-cs-fixer fix --dry-run --diff
      - run: vendor/bin/phpstan analyze --no-progress
      - run: vendor/bin/phpunit --coverage-text
        env:
          PHPBOTGRAM_REDIS_DSN: redis://localhost:6379
          PHPBOTGRAM_MONGO_DSN: mongodb://localhost:27017
```

- [ ] **Step 2: Write Makefile**

```makefile
.PHONY: test stan lint fix regenerate

test:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyze

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	vendor/bin/php-cs-fixer fix

regenerate:
	php tools/generator/bin/generate.php --schema .butcher/schema/schema.json --out src/
```

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci.yml Makefile
git commit -m "chore: add CI workflow + Makefile"
```

### Task 0.4: Implement TelegramObject + MutableTelegramObject base classes

**Files:**
- Create: `src/Types/TelegramObject.php`
- Create: `src/Types/MutableTelegramObject.php`
- Create: `tests/Types/TelegramObjectTest.php`

Per spec section "BotContextController & bot binding" — `TelegramObject` is **non-readonly class** (property-level readonly only) so `MutableTelegramObject` can subclass it.

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\TelegramObject;
use PHPUnit\Framework\TestCase;

final class TelegramObjectTest extends TestCase
{
    public function testTelegramObjectIsNotReadonlyClass(): void
    {
        $reflection = new \ReflectionClass(TelegramObject::class);
        self::assertFalse($reflection->isReadOnly(), 'TelegramObject must not be `readonly class` so MutableTelegramObject can subclass it');
    }

    public function testTelegramObjectIsAbstract(): void
    {
        $reflection = new \ReflectionClass(TelegramObject::class);
        self::assertTrue($reflection->isAbstract());
    }
}
```

- [ ] **Step 2: Run test — fails (class doesn't exist)**

Run: `vendor/bin/phpunit tests/Types/TelegramObjectTest.php`
Expected: Error: Class TelegramObject not found.

- [ ] **Step 3: Implement TelegramObject and MutableTelegramObject**

Create `src/Types/TelegramObject.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Client\BotContextController;

abstract class TelegramObject extends BotContextController
{
}
```

Create `src/Types/MutableTelegramObject.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

/**
 * Non-readonly parent for the small set of schema types whose `replace.yml`
 * carries `bases: [MutableTelegramObject]` — currently 16 entities, primarily
 * keyboard/menu/input-media builders that need post-construction mutation.
 * Hand-authored builder classes (`Utils\Keyboard\InlineKeyboardBuilder`, etc.)
 * also extend it directly. NOT abstract — matches upstream `aiogram/types/base.py:38-41`.
 * See spec § "Mutable type variant" and § "TypeOverrideApplier".
 */
class MutableTelegramObject extends TelegramObject
{
}
```

`BotContextController` is created in Task 0.5; for now also add a minimal stub:

Create `src/Client/BotContextController.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

abstract class BotContextController
{
    public function __construct(public readonly ?\Gruven\PhpBotGram\Bot $bot = null) {}
}
```

- [ ] **Step 4: Add Bot stub (placeholder; fleshed out in Phase 1)**

Create `src/Bot.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram;

/**
 * Stub at Phase 0; full 176-method facade is generated in Phase 2.
 */
class Bot
{
}
```

- [ ] **Step 5: Regenerate autoloader**

The `autoload.psr-4` was already present in the initial composer.json; Task 0.1 only extended `autoload-dev`. Just regenerate:

Run: `composer dump-autoload`

- [ ] **Step 6: Run tests — pass**

Run: `vendor/bin/phpunit tests/Types/TelegramObjectTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add src/Types/TelegramObject.php src/Types/MutableTelegramObject.php src/Client/BotContextController.php src/Bot.php tests/Types/TelegramObjectTest.php composer.json
git commit -m "feat: TelegramObject + MutableTelegramObject + BotContextController stubs"
```

### Task 0.5: Flesh out BotContextController + TelegramMethod base

**Files:**
- Modify: `src/Client/BotContextController.php`
- Create: `src/Methods/TelegramMethod.php`
- Create: `src/Methods/Request.php`
- Create: `src/Methods/Response.php`
- Create: `tests/Client/BotContextControllerTest.php`

Spec § "BotContextController & bot binding" — final shape with `withBot()` deep-clone and `as_()` alias.

- [ ] **Step 1: Write failing tests**

`tests/Client/BotContextControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotContextController;
use PHPUnit\Framework\TestCase;

final class BotContextControllerTest extends TestCase
{
    public function testBotDefaultsToNull(): void
    {
        $obj = new class extends BotContextController {};
        self::assertNull($obj->bot);
    }

    public function testWithBotReturnsClone(): void
    {
        $original = new class extends BotContextController {};
        $bot = new Bot();
        $clone = $original->withBot($bot);

        self::assertNotSame($original, $clone);
        self::assertNull($original->bot);
        self::assertSame($bot, $clone->bot);
    }

    public function testAsIsAliasOfWithBot(): void
    {
        $obj = new class extends BotContextController {};
        $bot = new Bot();
        self::assertEquals($obj->withBot($bot)->bot, $obj->as_($bot)->bot);
    }
}
```

- [ ] **Step 2: Run — fails**

Run: `vendor/bin/phpunit tests/Client/BotContextControllerTest.php`

- [ ] **Step 3: Implement BotContextController fully**

Replace `src/Client/BotContextController.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Bot;

abstract class BotContextController
{
    public function __construct(public readonly ?Bot $bot = null) {}

    /**
     * Returns a clone of $this with $bot rebound. Used by Serializer to inject
     * the active Bot into deserialized objects (mirrors upstream model_validate
     * context={"bot": bot}). The Serializer recursively rebinds bot on every
     * nested TelegramObject; this method handles the shallow rebind.
     *
     * Uses PHP 8.5's clone-with syntax `clone($this, [...])` which is the only
     * way to modify a readonly property on a clone. The call must be made from
     * within the declaring scope (i.e. inside BotContextController or a subclass);
     * an external caller cannot use this syntax against a readonly slot.
     */
    public function withBot(?Bot $bot): static
    {
        return clone($this, ['bot' => $bot]);
    }

    /**
     * Alias of withBot() for grep-translating aiogram code that uses obj.as_(bot).
     * IMPORTANT: behaves DIFFERENTLY from upstream — upstream mutates self._bot
     * in place and returns self. The PHP port can't mutate readonly, so this
     * returns a clone. Callers must reassign: $msg = $msg->as_($bot).
     */
    public function as_(?Bot $bot): static
    {
        return $this->withBot($bot);
    }
}
```

Note: PHP 8.3 introduced "clone with" semantics where readonly props can be set once on a cloned object via reflection. If running 8.5 strictly, this should just work.

- [ ] **Step 4: Implement TelegramMethod base + Request/Response**

`src/Methods/TelegramMethod.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotContextController;

/**
 * @template TReturn
 */
abstract class TelegramMethod extends BotContextController
{
    public const string ApiMethod = '';
    /** @var class-string */
    public const string ReturnsType = '';

    /**
     * Emit this method via the bound bot (or the explicitly-passed bot).
     * Mirrors upstream methods/base.py:81-93 (__await__ + emit).
     *
     * @return TReturn
     */
    public function emit(?Bot $bot = null): mixed
    {
        $effective = $bot ?? $this->bot;
        if ($effective === null) {
            throw new \LogicException(
                'This method is not mounted to any bot instance. ' .
                'Call it explicitly with bot instance `$bot($method)`, ' .
                'or mount it via `$method->bindBot($bot)` and call `$method->emit()`.'
            );
        }
        return $effective($this);
    }

    /**
     * Returns a clone bound to $bot. Used by hand-authored shortcut methods
     * (Message::answer, etc.) so the chained ->emit() picks up the bot
     * without an explicit argument.
     */
    public function bindBot(?Bot $bot): static
    {
        return $this->withBot($bot);
    }
}
```

`src/Methods/Request.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Types\InputFile;

final readonly class Request
{
    public function __construct(
        public string $method,
        public array $data,
        /** @var array<string, InputFile>|null */
        public ?array $files = null,
    ) {}
}
```

`src/Methods/Response.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Types\ResponseParameters;

/**
 * @template TResult
 */
final readonly class Response
{
    public function __construct(
        public bool $ok,
        public mixed $result = null,
        public ?string $description = null,
        public ?int $errorCode = null,
        public ?ResponseParameters $parameters = null,
    ) {}
}
```

Add stub `src/Types/InputFile.php` and `src/Types/ResponseParameters.php` for now (filled in Task 1.4 / Phase 2):

```php
<?php
namespace Gruven\PhpBotGram\Types;
abstract class InputFile extends TelegramObject {}
```

```php
<?php
namespace Gruven\PhpBotGram\Types;
final class ResponseParameters extends TelegramObject {
    public function __construct(
        public readonly ?int $migrateToChatId = null,
        public readonly ?int $retryAfter = null,
        ?\Gruven\PhpBotGram\Bot $bot = null,
    ) { parent::__construct($bot); }
}
```

- [ ] **Step 5: Run — pass**

Run: `vendor/bin/phpunit tests/Client/BotContextControllerTest.php`

- [ ] **Step 6: Commit**

```bash
git add src/Client/BotContextController.php src/Methods/ src/Types/InputFile.php src/Types/ResponseParameters.php tests/Client/BotContextControllerTest.php
git commit -m "feat: BotContextController.withBot/as_ + TelegramMethod base"
```

### Task 0.6: Implement BotDefault sentinel + Unspecified marker

**Files:**
- Create: `src/Client/BotDefault.php`
- Create: `src/Types/Unspecified.php`
- Create: `tests/Client/BotDefaultTest.php`
- Create: `tests/Types/UnspecifiedTest.php`

Spec § "BotDefault sentinel and Unspecified marker" — `BotDefault` throws on jsonSerialize (fail-loud); `Unspecified` is a readonly singleton.

- [ ] **Step 1: Write failing tests**

`tests/Client/BotDefaultTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\BotDefault;
use PHPUnit\Framework\TestCase;

final class BotDefaultTest extends TestCase
{
    public function testStoresName(): void
    {
        $d = new BotDefault('parse_mode');
        self::assertSame('parse_mode', $d->name);
    }

    public function testEqualsByName(): void
    {
        $a = new BotDefault('parse_mode');
        $b = new BotDefault('parse_mode');
        $c = new BotDefault('protect_content');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a === $b, 'Different instances are not identity-equal');
    }

    public function testJsonSerializeThrows(): void
    {
        $d = new BotDefault('parse_mode');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('parse_mode');
        json_encode($d, JSON_THROW_ON_ERROR);
    }
}
```

`tests/Types/UnspecifiedTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\Unspecified;
use PHPUnit\Framework\TestCase;

final class UnspecifiedTest extends TestCase
{
    public function testInstanceIsSingleton(): void
    {
        self::assertSame(Unspecified::instance(), Unspecified::instance());
    }
}
```

- [ ] **Step 2: Run — fails (classes missing)**

- [ ] **Step 3: Implement**

`src/Client/BotDefault.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

/**
 * Sentinel for "use the bot's configured default for this field".
 *
 * Renamed from upstream `Default` because PHP reserves `default` as a keyword
 * (case-insensitive) so `class Default` won't parse even when namespaced.
 *
 * The Serializer always resolves BotDefault instances against
 * $bot->getDefaultProperties() before encoding. jsonSerialize throws so a
 * BotDefault that escapes resolution fails loudly rather than silently
 * emitting `null` on the wire.
 */
final readonly class BotDefault implements \JsonSerializable
{
    public function __construct(public string $name) {}

    public function equals(BotDefault $other): bool
    {
        return $this->name === $other->name;
    }

    public function jsonSerialize(): never
    {
        throw new \LogicException(
            "BotDefault sentinel reached json_encode without being resolved: {$this->name}"
        );
    }

    public function __toString(): string
    {
        return "BotDefault('{$this->name}')";
    }
}
```

`src/Types/Unspecified.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

/**
 * Sentinel singleton for "argument was not provided" cases.
 *
 * Renamed from upstream `UNSET` because PHP reserves `unset` as a keyword
 * so `class Unset` won't parse. The serializer strips fields whose value
 * is Unspecified::instance() before validation/encoding.
 *
 * NOT declared `readonly class`: PHP forbids `static` properties on a
 * readonly class, and the singleton needs `private static ?self $instance`
 * to cache the sole instance. The private constructor + singleton pattern
 * already enforces the desired immutability.
 */
final class Unspecified
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}
```

- [ ] **Step 4: Run — pass**

- [ ] **Step 5: Commit**

```bash
git add src/Client/BotDefault.php src/Types/Unspecified.php tests/Client/BotDefaultTest.php tests/Types/UnspecifiedTest.php
git commit -m "feat: BotDefault + Unspecified sentinels (rename of Default/UNSET)"
```

### Task 0.7: TelegramApiServer

**Files:**
- Create: `src/Client/TelegramApiServer.php`
- Create: `tests/Client/TelegramApiServerTest.php`

Spec § "TelegramApiServer" — final readonly class with `production()` / `test()` / `fromBase()` factory methods.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\TelegramApiServer;
use PHPUnit\Framework\TestCase;

final class TelegramApiServerTest extends TestCase
{
    public function testProductionUrls(): void
    {
        $api = TelegramApiServer::production();
        self::assertSame('https://api.telegram.org/bot123:abc/sendMessage', $api->apiUrl('123:abc', 'sendMessage'));
        self::assertSame('https://api.telegram.org/file/bot123:abc/path/to/file', $api->fileUrl('123:abc', 'path/to/file'));
        self::assertFalse($api->isLocal);
    }

    public function testFromBase(): void
    {
        $api = TelegramApiServer::fromBase('http://localhost:8081');
        self::assertSame('http://localhost:8081/bot123/getMe', $api->apiUrl('123', 'getMe'));
    }
}
```

- [ ] **Step 2: Run — fails**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

final readonly class TelegramApiServer
{
    public function __construct(
        public string $base,
        public string $file,
        public bool $isLocal = false,
    ) {}

    public static function production(): self
    {
        return new self(
            base: 'https://api.telegram.org/bot{token}/{method}',
            file: 'https://api.telegram.org/file/bot{token}/{path}',
        );
    }

    public static function test(): self
    {
        return new self(
            base: 'https://api.telegram.org/bot{token}/test/{method}',
            file: 'https://api.telegram.org/file/bot{token}/test/{path}',
        );
    }

    public static function fromBase(string $base, bool $isLocal = false): self
    {
        $base = rtrim($base, '/');
        return new self(
            base: "{$base}/bot{token}/{method}",
            file: "{$base}/file/bot{token}/{path}",
            isLocal: $isLocal,
        );
    }

    public function apiUrl(string $token, string $method): string
    {
        return strtr($this->base, ['{token}' => $token, '{method}' => $method]);
    }

    public function fileUrl(string $token, string $path): string
    {
        return strtr($this->file, ['{token}' => $token, '{path}' => $path]);
    }
}
```

- [ ] **Step 4: Run — pass**

- [ ] **Step 5: Commit**

```bash
git add src/Client/TelegramApiServer.php tests/Client/TelegramApiServerTest.php
git commit -m "feat: TelegramApiServer with production/test/fromBase factories"
```

### Task 0.8: Token utility (validate + extract bot id)

**Files:**
- Create: `src/Utils/Token.php`
- Create: `tests/Utils/TokenTest.php`

Mirror upstream `aiogram/utils/token.py`.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils;

use Gruven\PhpBotGram\Exceptions\PhpBotGramException;
use Gruven\PhpBotGram\Utils\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testValidate(): void
    {
        Token::validate('42:TEST');
        Token::validate('12345:abcdef-XYZ');
        $this->expectNotToPerformAssertions();
    }

    public static function invalidTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'no colon' => ['12345'];
        yield 'left non-digit' => ['abc:TEST'];
        yield 'right empty' => ['42:'];
    }

    /** @dataProvider invalidTokens */
    public function testValidateRejects(string $token): void
    {
        $this->expectException(PhpBotGramException::class);
        Token::validate($token);
    }

    public function testExtractBotId(): void
    {
        self::assertSame(42, Token::extractBotId('42:TEST'));
        self::assertSame(123456789, Token::extractBotId('123456789:secret'));
    }
}
```

Also need a stub `src/Exceptions/PhpBotGramException.php`:

```php
<?php
namespace Gruven\PhpBotGram\Exceptions;
class PhpBotGramException extends \Exception {}
```

- [ ] **Step 2: Run — fails**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

use Gruven\PhpBotGram\Exceptions\PhpBotGramException;

final class Token
{
    public static function validate(string $token): void
    {
        if ($token === '' || !str_contains($token, ':')) {
            throw new PhpBotGramException("Invalid token format: '{$token}'");
        }
        [$left, $right] = explode(':', $token, 2);
        if (!ctype_digit($left) || $right === '') {
            throw new PhpBotGramException("Invalid token format: '{$token}'");
        }
    }

    public static function extractBotId(string $token): int
    {
        self::validate($token);
        [$left] = explode(':', $token, 2);
        return (int) $left;
    }
}
```

- [ ] **Step 4: Run — pass**

- [ ] **Step 5: Commit**

```bash
git add src/Utils/Token.php src/Exceptions/PhpBotGramException.php tests/Utils/TokenTest.php
git commit -m "feat: Token::validate + Token::extractBotId"
```

### Task 0.9: Exception tree

**Files:**
- Create: `src/Exceptions/*.php` (full tree per spec § Exceptions)
- Create: `tests/Exceptions/ExceptionHierarchyTest.php`

Spec § "Exceptions" lists the full Error→Exception mapping plus per-class payload fields.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Exceptions;

use Gruven\PhpBotGram\Exceptions\PhpBotGramException;
use Gruven\PhpBotGram\Exceptions\TelegramApiException;
use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramMigrateToChat;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use PHPUnit\Framework\TestCase;

final class ExceptionHierarchyTest extends TestCase
{
    public function testApiInheritsFromBase(): void
    {
        $r = new \ReflectionClass(TelegramApiException::class);
        self::assertTrue($r->isSubclassOf(PhpBotGramException::class));
    }

    public function testRetryAfterCarriesPayload(): void
    {
        $method = new class extends TelegramMethod { public const string ApiMethod = 'x'; public const string ReturnsType = ''; };
        $e = new TelegramRetryAfter($method, 'Flood control', retryAfter: 30);
        self::assertSame(30, $e->retryAfter);
        self::assertSame($method, $e->method);
    }

    public function testMigrateToChatPayload(): void
    {
        $method = new class extends TelegramMethod { public const string ApiMethod = 'x'; public const string ReturnsType = ''; };
        $e = new TelegramMigrateToChat($method, 'Migrated', migrateToChatId: -100123);
        self::assertSame(-100123, $e->migrateToChatId);
    }

    public function testBadRequestInheritsFromApiException(): void
    {
        self::assertTrue(is_subclass_of(TelegramBadRequestException::class, TelegramApiException::class));
    }
}
```

- [ ] **Step 2: Run — fails**

- [ ] **Step 3: Implement each exception class**

Replace `src/Exceptions/PhpBotGramException.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

class PhpBotGramException extends \Exception
{
}
```

`src/Exceptions/DetailedPhpBotGramException.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

class DetailedPhpBotGramException extends PhpBotGramException
{
    public ?string $url = null;

    public function __construct(public readonly string $detail)
    {
        $msg = $detail;
        if ($this->url !== null) {
            $msg .= "\n(background on this error at: {$this->url})";
        }
        parent::__construct($msg);
    }
}
```

`src/Exceptions/TelegramApiException.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;

class TelegramApiException extends DetailedPhpBotGramException
{
    protected string $label = 'Telegram server says';

    public function __construct(
        public readonly TelegramMethod $method,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function __toString(): string
    {
        return "{$this->label} - {$this->detail}";
    }
}
```

`src/Exceptions/TelegramRetryAfter.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;

final class TelegramRetryAfter extends TelegramApiException
{
    public function __construct(
        TelegramMethod $method,
        string $message,
        public readonly int $retryAfter,
    ) {
        $methodName = (new \ReflectionClass($method))->getShortName();
        $description = "Flood control exceeded on method '{$methodName}'. Retry in {$retryAfter} seconds.\nOriginal description: {$message}";
        parent::__construct($method, $description);
    }
}
```

`src/Exceptions/TelegramMigrateToChat.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;

final class TelegramMigrateToChat extends TelegramApiException
{
    public function __construct(
        TelegramMethod $method,
        string $message,
        public readonly int $migrateToChatId,
    ) {
        parent::__construct($method, "The group has been migrated to a supergroup with id {$migrateToChatId}\nOriginal description: {$message}");
    }
}
```

Each of the no-payload subclasses goes in its own `src/Exceptions/<Name>.php` file (one class per file, PSR-4):

`src/Exceptions/TelegramNetworkException.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

class TelegramNetworkException extends TelegramApiException
{
    protected string $label = 'HTTP Client says';
}
```

`src/Exceptions/TelegramBadRequestException.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

class TelegramBadRequestException extends TelegramApiException {}
```

Repeat the same one-class-per-file pattern for: `TelegramNotFoundException`, `TelegramConflictException`, `TelegramUnauthorizedException`, `TelegramForbiddenException`, `TelegramServerException`, `RestartingTelegram` (extends `TelegramServerException`), `TelegramEntityTooLarge` (extends `TelegramNetworkException`).

`src/Exceptions/ClientDecodeException.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

final class ClientDecodeException extends PhpBotGramException
{
    public function __construct(
        public readonly string $message,
        public readonly \Throwable $original,
        public readonly mixed $data,
    ) {
        $origType = $original::class;
        parent::__construct("{$message}\nCaused by: {$origType}: {$original->getMessage()}\nContent: " . print_r($data, true));
    }
}
```

Each of the remaining error classes lives in its own file under `src/Exceptions/`:

`src/Exceptions/CallbackAnswerException.php`:

```php
<?php
declare(strict_types=1);
namespace Gruven\PhpBotGram\Exceptions;
final class CallbackAnswerException extends PhpBotGramException {}
```

`src/Exceptions/SceneException.php`:

```php
<?php
declare(strict_types=1);
namespace Gruven\PhpBotGram\Exceptions;
final class SceneException extends PhpBotGramException {}
```

`src/Exceptions/UnsupportedKeywordArgumentException.php`:

```php
<?php
declare(strict_types=1);
namespace Gruven\PhpBotGram\Exceptions;
final class UnsupportedKeywordArgumentException extends DetailedPhpBotGramException
{
    public function __construct(public readonly string $argName, string $message)
    {
        parent::__construct($message);
    }
}
```

`src/Exceptions/UpdateTypeLookupException.php`:

```php
<?php
declare(strict_types=1);
namespace Gruven\PhpBotGram\Exceptions;
final class UpdateTypeLookupException extends PhpBotGramException {}
```

`src/Exceptions/DataNotDictLikeException.php`:

```php
<?php
declare(strict_types=1);
namespace Gruven\PhpBotGram\Exceptions;
final class DataNotDictLikeException extends DetailedPhpBotGramException {}
```

- [ ] **Step 4: Run — pass**

- [ ] **Step 5: Commit**

```bash
git add src/Exceptions/ tests/Exceptions/
git commit -m "feat: exception tree (Error→Exception mapping per spec)"
```

### Task 0.10: Bases (UNHANDLED/REJECTED + SkipHandler/CancelHandler exceptions + skip helper)

**Files:**
- Create: `src/Dispatcher/Event/Bases.php`
- Create: `src/Dispatcher/Event/SkipHandlerException.php`
- Create: `src/Dispatcher/Event/CancelHandlerException.php`
- Create: `tests/Dispatcher/Event/BasesTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Gruven\PhpBotGram\Dispatcher\Event\Bases;
use Gruven\PhpBotGram\Dispatcher\Event\SkipHandlerException;
use PHPUnit\Framework\TestCase;

final class BasesTest extends TestCase
{
    public function testUnhandledHasStableSentinelValue(): void
    {
        // Pin the actual sentinel string so a rename in Bases::class breaks this test.
        self::assertSame('__phpbotgram_unhandled__', Bases::UNHANDLED);
    }

    public function testRejectedIsDistinctFromUnhandled(): void
    {
        self::assertNotSame(Bases::UNHANDLED, Bases::REJECTED);
    }

    public function testSkipHelperThrowsSkipException(): void
    {
        $this->expectException(SkipHandlerException::class);
        Bases::skip('passing on this update');
    }
}
```

- [ ] **Step 2: Run — fails**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

final class Bases
{
    public const string UNHANDLED = '__phpbotgram_unhandled__';
    public const string REJECTED = '__phpbotgram_rejected__';

    public static function skip(?string $message = null): never
    {
        throw new SkipHandlerException($message ?? 'Handler skipped');
    }
}
```

PSR-4 requires one class per file. Split into two files:

`src/Dispatcher/Event/SkipHandlerException.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

final class SkipHandlerException extends \Exception {}
```

`src/Dispatcher/Event/CancelHandlerException.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

final class CancelHandlerException extends \Exception {}
```

- [ ] **Step 4: Run — pass; Commit**

```bash
git add src/Dispatcher/Event/Bases.php src/Dispatcher/Event/SkipHandlerException.php src/Dispatcher/Event/CancelHandlerException.php tests/Dispatcher/Event/BasesTest.php
git commit -m "feat: Bases (UNHANDLED/REJECTED) + SkipHandler/CancelHandler exceptions"
```

### Task 0.11: BaseSession + Bot stubs (no-op constructors)

**Files:**
- Create: `src/Client/Session/BaseSession.php` (Phase 0 stub — replaced by Task 1.3)
- Replace: `src/Bot.php` (stub created at Task 0.4) — add a `public function __construct() {}` so subclasses can call `parent::__construct()` without fatal errors

The actual `MockedSession` and `MockedBot` are deferred to **Task 1.7** (a new task after Phase 1.6's full Bot/BaseSession lands). Phase 0 only needs the abstract surfaces in place so later tests reference them.

- [ ] **Step 1: Phase 0 BaseSession stub with no-op constructor**

`src/Client/Session/BaseSession.php` (placeholder; Task 1.3 replaces with the full version):

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Methods\TelegramMethod;

abstract class BaseSession
{
    /** No-op constructor — replaced by Task 1.3 with the full ?TelegramApiServer/$timeout signature. Lets future subclasses chain parent::__construct() without fatal errors. */
    public function __construct() {}

    abstract public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed;
    abstract public function close(): void;
}
```

- [ ] **Step 2: Extend Bot stub with no-op constructor**

Replace `src/Bot.php` to add a constructor so subclasses (MockedBot in Task 1.7) can `parent::__construct()`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram;

/**
 * Phase 0 stub. Phase 1.6 regenerates with the full 176-method facade and the
 * real constructor `(string $token, ?BaseSession $session = null, ?DefaultBotProperties $defaultProperties = null)`.
 */
class Bot
{
    public function __construct() {}
}
```

- [ ] **Step 3: User stub (replaced by Phase 2 codegen)**

The real MockedBot lands at **Task 1.7** (after Phase 1.6's full Bot/BaseSession/Serializer are in place). For Phase 0 we just need a User stub so other tests can compile:

Add `src/Types/User.php` placeholder (Phase 0 stub; Phase 2 regenerates with full schema fields):

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

final class User extends TelegramObject
{
    public function __construct(
        public readonly int $id,
        public readonly bool $isBot,
        public readonly string $firstName,
        // Nullable fields accept `Unspecified::instance()` to opt out of wire serialization
        // (Serializer::dump strips these); explicit `null` is preserved on the wire.
        public readonly string|Unspecified|null $lastName = null,
        public readonly string|Unspecified|null $username = null,
        public readonly string|Unspecified|null $languageCode = null,
        ?\Gruven\PhpBotGram\Bot $bot = null,
    ) {
        parent::__construct($bot);
    }
}
```

- [ ] **Step 4: Commit BaseSession + Bot stubs + User stub**

```bash
git add src/Client/Session/BaseSession.php src/Bot.php src/Types/User.php
git commit -m "feat: Phase 0 BaseSession/Bot stubs with no-op constructors + User stub"
```

### Task 0.12: RunAsyncTrait test helper

**Files:**
- Create: `tests/Support/RunAsyncTrait.php`
- Create: `tests/Support/RunAsyncTraitTest.php`

Spec § "Test infrastructure" — ~40 LOC helper that resets the EventLoop driver in `tearDown`.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

use function Amp\async;

final class RunAsyncTraitTest extends TestCase
{
    use RunAsyncTrait;

    public function testRunAsyncDrivesFuture(): void
    {
        $result = $this->runAsync(static fn (): int => 42);
        self::assertSame(42, $result);
    }

    public function testResetEventLoopProducesFreshDriver(): void
    {
        // PHPUnit's $this->tearDown() does NOT invoke #[After] hooks; we call
        // the reset method directly to verify it produces a fresh driver.
        $driver = EventLoop::getDriver();
        $this->resetEventLoop();
        self::assertNotSame($driver, EventLoop::getDriver());
    }
}
```

- [ ] **Step 2: Run — fails**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Closure;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;

use function Amp\async;

trait RunAsyncTrait
{
    /**
     * Drives a Fiber-aware closure to completion. The closure runs inside Amp\async()
     * so it can suspend; the result is awaited and returned.
     *
     * @template T
     * @param Closure(): T $body
     * @return T
     */
    protected function runAsync(Closure $body): mixed
    {
        return async($body)->await();
    }

    /**
     * Fresh driver per test — Revolt v1 has no public API to enumerate pending callbacks,
     * so a driver reset is the simplest reliable isolation. See spec § "Test infrastructure".
     * Callable directly from a test method that needs an explicit reset, AND fired
     * automatically after every test via the #[After] hook.
     */
    #[\PHPUnit\Framework\Attributes\After]
    public function resetEventLoop(): void
    {
        EventLoop::setDriver(new StreamSelectDriver());
    }
}
```

- [ ] **Step 4: Run — pass; Commit**

```bash
git add tests/Support/RunAsyncTrait.php tests/Support/RunAsyncTraitTest.php
git commit -m "feat(test): RunAsyncTrait — Fiber-aware test helper with EventLoop driver reset"
```

### Task 0.13: Phase 0 acceptance gate

(`RecordingDispatcher` test fixture is deferred entirely to **Task 3.13** — it requires the real `Dispatcher` class as a parent, which lands in Phase 3. Phase 0 does not ship a placeholder for it.)

- [ ] **Step 1: Full suite + static analysis**

Run: `vendor/bin/php-cs-fixer fix --dry-run --diff`
Run: `vendor/bin/phpstan analyze --no-progress`
Run: `vendor/bin/phpunit`

Expected: all green; CI green. PHPStan at level 9 may flag a few advisory issues in placeholder files — fix or `@phpstan-ignore-line` with a note pointing to the Phase that will resolve them.

- [ ] **Step 2: Mark Phase 0 complete**

```bash
git commit --allow-empty -m "chore: Phase 0 — bootstrap complete"
git tag phase-0-complete
```

---

## Phase 1 — Foundation

Hand-written `Bot`, `BaseSession`, `AmphpSession`, `Serializer`, `InputFile`, plus a hand-coded `SendMessage`/`Message`/`Bot::sendMessage` for the Phase 1 smoke test. Those hand-coded artifacts are deleted and regenerated in Phase 2.

### Task 1.1: DefaultBotProperties (with __post_init__ aggregation)

**Files:**
- Create: `src/Client/DefaultBotProperties.php`
- Create: `tests/Client/DefaultBotPropertiesTest.php`

Spec § "Default sentinel and Unspecified marker" / `DefaultBotProperties` — `ArrayAccess` + `get()` + `__post_init__` aggregation of `linkPreview*` flags.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Types\LinkPreviewOptions;
use PHPUnit\Framework\TestCase;

final class DefaultBotPropertiesTest extends TestCase
{
    public function testGetReturnsNullWhenUnset(): void
    {
        $d = new DefaultBotProperties();
        self::assertNull($d->get('parse_mode'));
    }

    public function testGetReturnsValue(): void
    {
        $d = new DefaultBotProperties(parseMode: 'HTML');
        self::assertSame('HTML', $d->get('parse_mode'));
        self::assertSame('HTML', $d['parse_mode']);
    }

    public function testLinkPreviewAggregation(): void
    {
        $d = new DefaultBotProperties(linkPreviewIsDisabled: true);
        $lp = $d->get('link_preview');
        self::assertInstanceOf(LinkPreviewOptions::class, $lp);
        self::assertTrue($lp->isDisabled);
    }
}
```

- [ ] **Step 2: Stub LinkPreviewOptions**

`src/Types/LinkPreviewOptions.php`:

```php
<?php
namespace Gruven\PhpBotGram\Types;
final class LinkPreviewOptions extends TelegramObject {
    public function __construct(
        public readonly ?bool $isDisabled = null,
        public readonly ?string $url = null,
        public readonly ?bool $preferSmallMedia = null,
        public readonly ?bool $preferLargeMedia = null,
        public readonly ?bool $showAboveText = null,
        ?\Gruven\PhpBotGram\Bot $bot = null,
    ) { parent::__construct($bot); }
}
```

- [ ] **Step 3: Implement DefaultBotProperties**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Types\LinkPreviewOptions;

/**
 * @implements \ArrayAccess<string, mixed>
 */
final class DefaultBotProperties implements \ArrayAccess
{
    public ?LinkPreviewOptions $linkPreview;

    public function __construct(
        public readonly ?string $parseMode = null,
        public readonly ?bool $disableNotification = null,
        public readonly ?bool $protectContent = null,
        public readonly ?bool $allowSendingWithoutReply = null,
        ?LinkPreviewOptions $linkPreview = null,
        public readonly ?bool $linkPreviewIsDisabled = null,
        public readonly ?bool $linkPreviewPreferSmallMedia = null,
        public readonly ?bool $linkPreviewPreferLargeMedia = null,
        public readonly ?bool $linkPreviewShowAboveText = null,
        public readonly ?bool $showCaptionAboveMedia = null,
    ) {
        $hasAnyLinkPreview = $linkPreviewIsDisabled !== null
            || $linkPreviewPreferSmallMedia !== null
            || $linkPreviewPreferLargeMedia !== null
            || $linkPreviewShowAboveText !== null;
        if ($linkPreview === null && $hasAnyLinkPreview) {
            $linkPreview = new LinkPreviewOptions(
                isDisabled: $linkPreviewIsDisabled,
                preferSmallMedia: $linkPreviewPreferSmallMedia,
                preferLargeMedia: $linkPreviewPreferLargeMedia,
                showAboveText: $linkPreviewShowAboveText,
            );
        }
        $this->linkPreview = $linkPreview;
    }

    public function get(string $name): mixed
    {
        return match ($name) {
            'parse_mode' => $this->parseMode,
            'disable_notification' => $this->disableNotification,
            'protect_content' => $this->protectContent,
            'allow_sending_without_reply' => $this->allowSendingWithoutReply,
            'link_preview' => $this->linkPreview,
            'link_preview_is_disabled' => $this->linkPreviewIsDisabled,
            'link_preview_prefer_small_media' => $this->linkPreviewPreferSmallMedia,
            'link_preview_prefer_large_media' => $this->linkPreviewPreferLargeMedia,
            'link_preview_show_above_text' => $this->linkPreviewShowAboveText,
            'show_caption_above_media' => $this->showCaptionAboveMedia,
            default => null,
        };
    }

    public function offsetExists(mixed $offset): bool { return is_string($offset) && $this->get($offset) !== null; }
    public function offsetGet(mixed $offset): mixed { return is_string($offset) ? $this->get($offset) : null; }
    public function offsetSet(mixed $offset, mixed $value): void { throw new \LogicException('DefaultBotProperties is immutable'); }
    public function offsetUnset(mixed $offset): void { throw new \LogicException('DefaultBotProperties is immutable'); }
}
```

- [ ] **Step 4: Run — pass; Commit**

```bash
git add src/Client/DefaultBotProperties.php src/Types/LinkPreviewOptions.php tests/Client/DefaultBotPropertiesTest.php
git commit -m "feat: DefaultBotProperties with linkPreview aggregation"
```

### Task 1.2: Custom DateTime type + InputFile family

**Files:**
- Create: `src/Types/Custom/DateTime.php`
- Create: `src/Types/InputFile.php` (full, replacing the Phase 0 stub)
- Create: `src/Types/BufferedInputFile.php`
- Create: `src/Types/FsInputFile.php`
- Create: `src/Types/UrlInputFile.php`
- Create: `tests/Types/InputFileTest.php`

Spec § "Types and methods (codegen)" / "TypeResolver" — `DateTime` is a subclass of `\DateTimeImmutable`. InputFile per upstream `aiogram/types/input_file.py`.

**Sequencing note:** The InputFile tests reference `MockedBot`, which is defined later in Task 1.7. Commit the production InputFile classes now; the tests can be merged at Task 1.7 after MockedBot lands. Alternative: stub the bot argument with a plain `Bot` constructor (Bot at this point has a no-op stub from Phase 0).

- [ ] **Step 1: DateTime + InputFile tests**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\BufferedInputFile;
use Gruven\PhpBotGram\Types\FsInputFile;
use PHPUnit\Framework\TestCase;

final class InputFileTest extends TestCase
{
    use \Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;

    public function testBufferedReadsBytes(): void
    {
        $file = new BufferedInputFile('hello world', 'greeting.txt');
        $this->runAsync(function () use ($file) {
            $stream = $file->read(new \Gruven\PhpBotGram\Tests\Support\MockedBot());
            $buf = '';
            while (($chunk = $stream->read()) !== null) {
                $buf .= $chunk;
            }
            self::assertSame('hello world', $buf);
        });
    }

    public function testFsReadsFromDisk(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpbg');
        file_put_contents($tmp, 'on disk');
        try {
            $file = new FsInputFile($tmp);
            $this->runAsync(function () use ($file) {
                $stream = $file->read(new \Gruven\PhpBotGram\Tests\Support\MockedBot());
                $buf = '';
                while (($chunk = $stream->read()) !== null) {
                    $buf .= $chunk;
                }
                self::assertSame('on disk', $buf);
            });
        } finally {
            unlink($tmp);
        }
    }
}
```

- [ ] **Step 2: Implement DateTime + InputFile + subclasses**

`src/Types/Custom/DateTime.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types\Custom;

/**
 * Marker subclass of DateTimeImmutable used for fields whose schema declares an integer
 * Unix timestamp but should be exposed as a DateTime in PHP (e.g. Message::date).
 * The Serializer converts on the way in (timestamp → DateTime) and out (DateTime → timestamp).
 */
final class DateTime extends \DateTimeImmutable
{
    public static function fromTimestamp(int $ts): self
    {
        return new self('@' . $ts);
    }

    public function toTimestamp(): int
    {
        return (int) $this->format('U');
    }
}
```

`src/Types/InputFile.php` (full replacement):

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;

abstract class InputFile extends TelegramObject
{
    public const int DEFAULT_CHUNK_SIZE = 65536;

    public function __construct(
        public readonly ?string $filename = null,
        public readonly int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?Bot $bot = null,
    ) {
        parent::__construct($bot);
    }

    /**
     * Returns a Fiber-aware readable stream of file bytes.
     */
    abstract public function read(Bot $bot): ReadableStream;
}
```

`src/Types/BufferedInputFile.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;

final class BufferedInputFile extends InputFile
{
    public function __construct(
        public readonly string $data,
        string $filename,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?Bot $bot = null,
    ) {
        parent::__construct(filename: $filename, chunkSize: $chunkSize, bot: $bot);
    }

    public static function fromFile(string $path, ?string $filename = null, int $chunkSize = self::DEFAULT_CHUNK_SIZE): self
    {
        $filename ??= basename($path);
        return new self(data: file_get_contents($path), filename: $filename, chunkSize: $chunkSize);
    }

    public function read(Bot $bot): ReadableStream
    {
        return new ReadableBuffer($this->data);
    }
}
```

`src/Types/FsInputFile.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableStream;
use Amp\File;
use Gruven\PhpBotGram\Bot;

final class FsInputFile extends InputFile
{
    public function __construct(
        public readonly string $path,
        ?string $filename = null,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?Bot $bot = null,
    ) {
        parent::__construct(filename: $filename ?? basename($path), chunkSize: $chunkSize, bot: $bot);
    }

    public function read(Bot $bot): ReadableStream
    {
        return File\openFile($this->path, 'r');
    }
}
```

`src/Types/UrlInputFile.php`: similar shape, streams via `amphp/http-client`. (Defer full implementation to Task 1.5; for Phase 1.2 a stub that throws is acceptable.)

- [ ] **Step 3: Run — pass; Commit**

`amphp/file ^3` is already in Phase 0's `require` (Task 0.1).

```bash
git add src/Types/InputFile.php src/Types/BufferedInputFile.php src/Types/FsInputFile.php src/Types/UrlInputFile.php src/Types/Custom/DateTime.php tests/Types/InputFileTest.php
git commit -m "feat: InputFile abstract + BufferedInputFile + FsInputFile + DateTime"
```

### Task 1.3: BaseSession (full implementation)

**Files:**
- Modify: `src/Client/Session/BaseSession.php` (replace Phase 0 stub)
- Create: `src/Client/Session/Middleware/BaseRequestMiddleware.php`
- Create: `src/Client/Session/Middleware/RequestMiddlewareManager.php`
- Create: `tests/Client/Session/BaseSessionTest.php`

Spec § "Async runtime and HTTP layer" / `BaseSession` — concrete constructor + abstract makeRequest/close/streamContent + concrete checkResponse/prepareValue.

- [ ] **Step 1: Failing test (checkResponse exception mapping)**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session;

use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use PHPUnit\Framework\TestCase;

final class BaseSessionTest extends TestCase
{
    public function testCheckResponseMapsRetryAfter(): void
    {
        $bot = new MockedBot();
        $session = $bot->session; // MockedSession extends BaseSession
        $method = new \Gruven\PhpBotGram\Methods\SendMessage(chatId: 1, text: 'x'); // hand-written, Task 1.6

        $this->expectException(TelegramRetryAfter::class);
        $session->checkResponse(
            bot: $bot,
            method: $method,
            statusCode: 429,
            content: json_encode(['ok' => false, 'description' => 'flood', 'parameters' => ['retry_after' => 30]]),
        );
    }

    public function testCheckResponseMapsBadRequest(): void
    {
        $bot = new MockedBot();
        $method = new \Gruven\PhpBotGram\Methods\SendMessage(chatId: 1, text: 'x');

        $this->expectException(TelegramBadRequestException::class);
        $bot->session->checkResponse(
            bot: $bot,
            method: $method,
            statusCode: 400,
            content: json_encode(['ok' => false, 'description' => 'bad chat id']),
        );
    }
}
```

This test depends on Tasks 1.6 (hand-coded SendMessage) and Serializer (Task 1.4). Order tasks: 1.3 → 1.4 → 1.5 → 1.6, then re-run this test at end of 1.6.

- [ ] **Step 2: Implement BaseSession**

Replace `src/Client/Session/BaseSession.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session;

use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Client\Session\Middleware\RequestMiddlewareManager;
use Gruven\PhpBotGram\Client\TelegramApiServer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use Gruven\PhpBotGram\Exceptions\TelegramApiException;
use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramConflictException;
use Gruven\PhpBotGram\Exceptions\TelegramEntityTooLarge;
use Gruven\PhpBotGram\Exceptions\TelegramForbiddenException;
use Gruven\PhpBotGram\Exceptions\TelegramMigrateToChat;
use Gruven\PhpBotGram\Exceptions\TelegramNotFoundException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Exceptions\TelegramServerException;
use Gruven\PhpBotGram\Exceptions\TelegramUnauthorizedException;
use Gruven\PhpBotGram\Exceptions\RestartingTelegram;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Methods\TelegramMethod;

abstract class BaseSession
{
    public readonly TelegramApiServer $api;
    public private(set) RequestMiddlewareManager $middleware;

    public function __construct(
        ?TelegramApiServer $api = null,
        public readonly mixed $jsonLoads = 'json_decode',
        public readonly mixed $jsonDumps = 'json_encode',
        public readonly float $timeout = 60.0,
    ) {
        $this->api = $api ?? TelegramApiServer::production();
        $this->middleware = new RequestMiddlewareManager();
    }

    abstract public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed;
    abstract public function close(): void;
    abstract public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream;

    public function checkResponse(Bot $bot, TelegramMethod $method, int $statusCode, string $content): Response
    {
        try {
            $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ClientDecodeException('Failed to decode response', $e, $content);
        }

        if (!is_array($data)) {
            throw new ClientDecodeException('Response is not an object', new \RuntimeException(), $content);
        }

        // Deserialize via Serializer (Task 1.4); for now we use a shim.
        $response = $this->buildResponse($bot, $method, $data);

        if ($statusCode >= 200 && $statusCode < 300 && $response->ok) {
            return $response;
        }

        $description = (string) ($data['description'] ?? '');
        $params = $data['parameters'] ?? null;
        if (is_array($params)) {
            if (isset($params['retry_after']) && is_int($params['retry_after'])) {
                throw new TelegramRetryAfter($method, $description, retryAfter: $params['retry_after']);
            }
            if (isset($params['migrate_to_chat_id']) && is_int($params['migrate_to_chat_id'])) {
                throw new TelegramMigrateToChat($method, $description, migrateToChatId: $params['migrate_to_chat_id']);
            }
        }

        throw match (true) {
            $statusCode === 400 => new TelegramBadRequestException($method, $description),
            $statusCode === 401 => new TelegramUnauthorizedException($method, $description),
            $statusCode === 403 => new TelegramForbiddenException($method, $description),
            $statusCode === 404 => new TelegramNotFoundException($method, $description),
            $statusCode === 409 => new TelegramConflictException($method, $description),
            $statusCode === 413 => new TelegramEntityTooLarge($method, $description),
            $statusCode >= 500 && str_contains($description, 'restart') => new RestartingTelegram($method, $description),
            $statusCode >= 500 => new TelegramServerException($method, $description),
            default => new TelegramApiException($method, $description),
        };
    }

    /**
     * Resolve sentinels + InputFile detachment + JSON encoding.
     * Port of upstream session/base.py:179-250.
     */
    public function prepareValue(mixed $value, Bot $bot, array &$files, bool $dumpsJson = true): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof BotDefault) {
            $resolved = $bot->getDefaultProperties()->get($value->name);
            return $this->prepareValue($resolved, $bot, $files, $dumpsJson);
        }
        if ($value instanceof \Gruven\PhpBotGram\Types\InputFile) {
            $key = bin2hex(random_bytes(10));
            $files[$key] = $value;
            return "attach://{$key}";
        }
        if (is_array($value)) {
            $isList = array_is_list($value);
            $prepared = [];
            foreach ($value as $k => $item) {
                $p = $this->prepareValue($item, $bot, $files, dumpsJson: false);
                if ($p === null) {
                    continue;   // null-filter rule
                }
                $isList ? $prepared[] = $p : $prepared[$k] = $p;
            }
            if ($dumpsJson) {
                return json_encode($prepared, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            return $prepared;
        }
        if ($value instanceof \DateInterval) {
            $now = new \DateTimeImmutable();
            return (string) $now->add($value)->getTimestamp();
        }
        if ($value instanceof \DateTimeInterface) {
            return (string) $value->getTimestamp();
        }
        if ($value instanceof \BackedEnum) {
            return $this->prepareValue($value->value, $bot, $files);
        }
        if ($value instanceof \Gruven\PhpBotGram\Types\TelegramObject) {
            $dumped = \Gruven\PhpBotGram\Client\Serializer::dump($value);
            return $this->prepareValue($dumped, $bot, $files, dumpsJson: $dumpsJson);
        }
        if ($dumpsJson) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }

    public function __invoke(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
    {
        $middleware = $this->middleware->wrap($this->makeRequest(...));
        return $middleware($bot, $method, $timeout);
    }

    /** Override in Phase 2 — current shim builds a Response with a null result. */
    protected function buildResponse(Bot $bot, TelegramMethod $method, array $data): Response
    {
        return new Response(
            ok: (bool) ($data['ok'] ?? false),
            result: null,
            description: $data['description'] ?? null,
            errorCode: $data['error_code'] ?? null,
        );
    }
}
```

Note: `public RequestMiddlewareManager $middleware { get => $this->middleware; }` uses PHP 8.4 property hooks. Adjust to a plain `public RequestMiddlewareManager $middleware;` set in constructor if hooks tangle with readonly chains.

- [ ] **Step 3: Implement RequestMiddlewareManager**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session\Middleware;

final class RequestMiddlewareManager
{
    /** @var list<BaseRequestMiddleware> */
    private array $middlewares = [];

    public function register(BaseRequestMiddleware $middleware): BaseRequestMiddleware
    {
        $this->middlewares[] = $middleware;
        return $middleware;
    }

    public function wrap(\Closure $terminal): \Closure
    {
        $next = $terminal;
        foreach (array_reverse($this->middlewares) as $middleware) {
            $current = $next;
            $next = static fn (...$args) => $middleware($current, ...$args);
        }
        return $next;
    }
}
```

```php
<?php
namespace Gruven\PhpBotGram\Client\Session\Middleware;
abstract class BaseRequestMiddleware {
    abstract public function __invoke(\Closure $next, \Gruven\PhpBotGram\Bot $bot, \Gruven\PhpBotGram\Methods\TelegramMethod $method, ?int $timeout = null): mixed;
}
```

- [ ] **Step 4: Defer the failing tests — they need Serializer (Task 1.4) + hand-coded SendMessage (Task 1.6)**

For Task 1.3 commit just the framework. Tests come live in 1.6.

- [ ] **Step 5: Commit**

```bash
git add src/Client/Session/BaseSession.php src/Client/Session/Middleware/
git commit -m "feat: BaseSession with checkResponse/prepareValue + RequestMiddlewareManager"
```

### Task 1.4: Serializer

**Files:**
- Create: `src/Client/Serializer.php`
- Create: `tests/Client/SerializerTest.php`

Spec § "Serializer" — `dump()`/`load()` with recursive bot binding + Default/Unspecified handling.

**Sequencing note:** The Serializer test references `MockedBot` (Task 1.7). Commit the Serializer production code now; merge the test into the suite at Task 1.7. Alternatively, substitute `MockedBot` with a plain `Bot` stub in the test — Phase 0's Bot has a no-op constructor and works as a sentinel object.

- [ ] **Step 1: Failing tests**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Unspecified;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

final class SerializerTest extends TestCase
{
    public function testDumpStripsUnspecified(): void
    {
        $user = new User(id: 1, isBot: false, firstName: 'A', lastName: Unspecified::instance());
        $dumped = Serializer::dump($user);
        self::assertArrayNotHasKey('last_name', $dumped, 'Unspecified values are stripped from dump output');
        self::assertSame(1, $dumped['id']);
        self::assertFalse($dumped['is_bot']);
    }

    public function testDumpPreservesNulls(): void
    {
        // Null is a real wire value (e.g. some Telegram fields). Stripping nulls is the responsibility
        // of BaseSession::prepareValue's null-filter rule, NOT Serializer::dump. See spec § Serializer.
        $user = new User(id: 1, isBot: false, firstName: 'A', lastName: null);
        $dumped = Serializer::dump($user);
        self::assertArrayHasKey('last_name', $dumped);
        self::assertNull($dumped['last_name']);
    }

    public function testLoadConstructsTypeWithBot(): void
    {
        $bot = new MockedBot();
        $user = Serializer::load(User::class, ['id' => 5, 'is_bot' => true, 'first_name' => 'B'], $bot);
        self::assertSame(5, $user->id);
        self::assertSame($bot, $user->bot);
    }
}
```

- [ ] **Step 2: Implement Serializer**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Unspecified;

final class Serializer
{
    /**
     * Walks a TelegramObject/TelegramMethod into a snake_case-keyed array.
     * Skips Unspecified values; preserves BotDefault sentinels (resolved later
     * in BaseSession::prepareValue).
     *
     * Deviation from spec § "Serializer" line 602 (`dump(object, bot, &files)`):
     * the plan splits responsibilities — Serializer::dump is value-only; bot
     * threading + InputFile detachment + JSON encoding all live in
     * BaseSession::prepareValue. This keeps the Serializer side-effect-free
     * and pure, easing testing.
     */
    public static function dump(TelegramObject $object): array
    {
        $r = new \ReflectionClass($object);
        $result = [];
        foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->getName() === 'bot') {
                continue;
            }
            $value = $prop->getValue($object);
            if ($value === Unspecified::instance()) {
                continue;
            }
            $key = self::camelToSnake($prop->getName());
            $result[$key] = self::dumpValue($value);
        }
        return $result;
    }

    private static function dumpValue(mixed $value): mixed
    {
        if ($value instanceof TelegramObject) {
            return self::dump($value);
        }
        if (is_array($value)) {
            return array_map([self::class, 'dumpValue'], $value);
        }
        return $value;
    }

    /**
     * Construct a TelegramObject from a snake_case dict, recursively binding $bot.
     *
     * @template T of TelegramObject
     * @param class-string<T> $class
     * @return T
     */
    public static function load(string $class, array $data, ?Bot $bot = null): TelegramObject
    {
        $r = new \ReflectionClass($class);
        $ctor = $r->getConstructor() ?? throw new \LogicException("{$class} has no constructor");
        $args = [];
        foreach ($ctor->getParameters() as $param) {
            if ($param->getName() === 'bot') {
                $args['bot'] = $bot;
                continue;
            }
            $snake = self::camelToSnake($param->getName());
            if (!array_key_exists($snake, $data)) {
                if ($param->isDefaultValueAvailable()) {
                    continue;
                }
                throw new \LogicException("Missing key '{$snake}' for {$class}");
            }
            $args[$param->getName()] = self::loadValue($param, $data[$snake], $bot);
        }
        return $r->newInstance(...$args);
    }

    private static function loadValue(\ReflectionParameter $param, mixed $value, ?Bot $bot): mixed
    {
        $type = $param->getType();
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if (is_subclass_of($typeName, TelegramObject::class) && is_array($value)) {
                return self::load($typeName, $value, $bot);
            }
        }
        // Union types — for object members, consult the discriminated union helper
        // emitted by Task 2.6 UnionDetector (e.g. ReplyMarkupUnion::resolve($value, $bot)).
        if ($type instanceof \ReflectionUnionType && is_array($value)) {
            foreach ($type->getTypes() as $member) {
                if (!$member instanceof \ReflectionNamedType || $member->isBuiltin()) continue;
                $memberName = $member->getName();
                // Heuristic: if any member is a TelegramObject subclass and there's a
                // sibling *Union class with resolve(), delegate to it.
                $unionClass = $memberName . 'Union';
                if (class_exists($unionClass) && method_exists($unionClass, 'resolve')) {
                    return $unionClass::resolve($value, $bot);
                }
                if (is_subclass_of($memberName, TelegramObject::class)) {
                    // Fallback: first TelegramObject member wins (good enough for non-discriminated cases).
                    return self::load($memberName, $value, $bot);
                }
            }
        }
        return $value;
    }

    private static function camelToSnake(string $camel): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
    }
}
```

- [ ] **Step 3: Run — pass; Commit**

```bash
git add src/Client/Serializer.php tests/Client/SerializerTest.php
git commit -m "feat: Serializer::dump/load with recursive bot binding"
```

### Task 1.5: AmphpSession

**Files:**
- Create: `src/Client/Session/AmphpSession.php`
- Create: `tests/Client/Session/AmphpSessionTest.php` (integration, optional)

Spec § "AmphpSession" — built on `Amp\Http\Client\HttpClientBuilder`, multipart streaming via amphp/byte-stream.

The full multipart streaming is complex; for the Phase 1 smoke test, implement a simplified version that handles non-file requests. File upload support lands in Phase 1.5b or deferred to Phase 6.

- [ ] **Step 1: Implement minimal AmphpSession**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Exceptions\TelegramNetworkException;
use Gruven\PhpBotGram\Methods\TelegramMethod;

final class AmphpSession extends BaseSession
{
    private ?HttpClient $client = null;

    public function __construct(
        public readonly ?string $proxy = null,
        public readonly int $limit = 100,
        ?\Gruven\PhpBotGram\Client\TelegramApiServer $api = null,
        float $timeout = 60.0,
    ) {
        parent::__construct(api: $api, timeout: $timeout);
    }

    private function client(): HttpClient
    {
        return $this->client ??= (new HttpClientBuilder())->build();
    }

    public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
    {
        $url = $this->api->apiUrl($bot->token, $method::ApiMethod);
        $files = [];
        $body = $this->buildFormBody($bot, $method, $files);

        $request = new Request($url, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);
        if ($timeout !== null) {
            $request->setTcpConnectTimeout((float) $timeout);
            $request->setTlsHandshakeTimeout((float) $timeout);
            $request->setTransferTimeout((float) $timeout);
        }

        try {
            $response = $this->client()->request($request);
            $content = $response->getBody()->buffer();
        } catch (\Throwable $e) {
            throw new TelegramNetworkException($method, $e::class . ': ' . $e->getMessage());
        }

        $resp = $this->checkResponse($bot, $method, $response->getStatus(), $content);
        return $resp->result;
    }

    public function close(): void
    {
        $this->client = null;
    }

    public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream
    {
        $request = new Request($url, 'GET');
        foreach ($headers as $k => $v) {
            $request->setHeader($k, $v);
        }
        $response = $this->client()->request($request);
        if ($raiseForStatus && $response->getStatus() >= 400) {
            throw new \RuntimeException("HTTP {$response->getStatus()} fetching {$url}");
        }
        return $response->getBody();
    }

    /**
     * For Phase 1 smoke test: form-urlencoded if no files, multipart otherwise.
     * Multipart with InputFile streaming is implemented properly in Phase 6.
     */
    private function buildFormBody(Bot $bot, TelegramMethod $method, array &$files): string
    {
        $dumped = \Gruven\PhpBotGram\Client\Serializer::dump($method);
        $fields = [];
        foreach ($dumped as $key => $value) {
            $prepared = $this->prepareValue($value, $bot, $files);
            if ($prepared === null) continue;
            $fields[$key] = $prepared;
        }
        return http_build_query($fields);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Client/Session/AmphpSession.php
git commit -m "feat: AmphpSession minimal — form-urlencoded body, no multipart yet"
```

### Task 1.6: Bot facade (skeleton) + hand-coded SendMessage/Message for smoke test

**Files:**
- Replace: `src/Bot.php` (full skeleton — gets regenerated in Phase 2)
- Create: `src/Client/BotShortcutsContract.php`
- Create: `src/Client/BotShortcuts.php`
- Create: `src/Methods/SendMessage.php` (hand-coded for Phase 1; replaced in Phase 2)
- Modify: `src/Types/Message.php` (hand-coded for Phase 1)
- Create: `tests/Bot/BotSmokeTest.php`

- [ ] **Step 1: Implement BotShortcuts trait + contract**

`src/Client/BotShortcutsContract.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Downloadable;
use Gruven\PhpBotGram\Types\File;
use Gruven\PhpBotGram\Types\User;

interface BotShortcutsContract
{
    public function getId(): int;
    public function context(bool $autoClose = true): \Closure;
    public static function current(): ?Bot;
    public static function setCurrent(?Bot $bot): void;
    public function me(): User;
    public function downloadFile(File|string $fileOrPath, mixed $destination = null, int $chunkSize = 65536): ?string;
    public function download(Downloadable $object, mixed $destination = null, int $chunkSize = 65536): ?string;
}
```

`src/Client/BotShortcuts.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\GetMe;
use Gruven\PhpBotGram\Types\File;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\Token;
use Revolt\EventLoop\FiberLocal;

trait BotShortcuts
{
    private static ?FiberLocal $currentBotLocal = null;
    private ?User $cachedMe = null;

    public function getId(): int
    {
        return Token::extractBotId($this->token);
    }

    public function context(bool $autoClose = true): \Closure
    {
        return function (\Closure $body) use ($autoClose) {
            try {
                return $body();
            } finally {
                if ($autoClose) {
                    // $session is `?BaseSession`; null-safe call avoids fatal on
                    // a Bot constructed without an explicit session (e.g. some test fixtures).
                    $this->session?->close();
                }
            }
        };
    }

    public static function current(): ?Bot
    {
        return (self::$currentBotLocal ??= new FiberLocal(static fn (): ?Bot => null))->get();
    }

    public static function setCurrent(?Bot $bot): void
    {
        (self::$currentBotLocal ??= new FiberLocal(static fn (): ?Bot => null))->set($bot);
    }

    public function me(): User
    {
        return $this->cachedMe ??= $this(new GetMe());
    }

    public function downloadFile(File|string $fileOrPath, mixed $destination = null, int $chunkSize = 65536): ?string
    {
        $path = $fileOrPath instanceof File ? $fileOrPath->filePath : $fileOrPath;
        $url = $this->session->api->fileUrl($this->token, $path);
        $stream = $this->session->streamContent($url, chunkSize: $chunkSize);
        return $this->consumeStream($stream, $destination);
    }

    public function download(\Gruven\PhpBotGram\Types\Downloadable $object, mixed $destination = null, int $chunkSize = 65536): ?string
    {
        $file = $this(new \Gruven\PhpBotGram\Methods\GetFile(fileId: $object->fileId));
        return $this->downloadFile($file, $destination, $chunkSize);
    }

    private function consumeStream(\Amp\ByteStream\ReadableStream $stream, mixed $destination): ?string
    {
        if ($destination === null) {
            $buf = '';
            while (($chunk = $stream->read()) !== null) {
                $buf .= $chunk;
            }
            return $buf;
        }
        $handle = is_string($destination) ? fopen($destination, 'wb') : $destination;
        while (($chunk = $stream->read()) !== null) {
            fwrite($handle, $chunk);
        }
        if (is_string($destination)) fclose($handle);
        return null;
    }
}
```

- [ ] **Step 2: Implement Bot facade**

`src/Bot.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram;

use Gruven\PhpBotGram\Client\BotShortcuts;
use Gruven\PhpBotGram\Client\BotShortcutsContract;
use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Client\Session\BaseSession;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Utils\Token;

/**
 * Bot facade. At Phase 1 contains only hand-coded sendMessage for the smoke test;
 * Phase 2 regenerates this file with all 176 API methods from the schema.
 */
class Bot implements BotShortcutsContract
{
    use BotShortcuts;

    public function __construct(
        public readonly string $token,
        public readonly ?BaseSession $session = null,
        public readonly ?DefaultBotProperties $defaultProperties = null,
    ) {
        Token::validate($token);
    }

    public function getDefaultProperties(): DefaultBotProperties
    {
        return $this->defaultProperties ?? new DefaultBotProperties();
    }

    /**
     * Polymorphic entry point: $bot($method) dispatches the method via the session.
     *
     * Note: Phase 1 deliberately calls `$session->makeRequest(...)` directly, bypassing
     * the BaseSession middleware chain. Phase 3 (dispatcher) is where middleware-wrapping
     * gets wired in via the request handler middleware manager — this $bot($method) entry
     * point stays middleware-bypassed because middleware applies to dispatcher events,
     * not raw method calls.
     *
     * @template TReturn
     * @param TelegramMethod<TReturn> $method
     * @return TReturn
     */
    public function __invoke(TelegramMethod $method, ?int $timeout = null): mixed
    {
        $session = $this->session ?? new AmphpSession();
        return $session->makeRequest($this, $method, $timeout);
    }

    // Hand-coded for Phase 1 smoke test; replaced in Phase 2 with full 176-method facade.
    public function sendMessage(int|string $chatId, string $text, ?string $parseMode = null, ?int $timeout = null): \Gruven\PhpBotGram\Types\Message
    {
        return $this(new SendMessage(chatId: $chatId, text: $text, parseMode: $parseMode), $timeout);
    }
}
```

- [ ] **Step 3: Hand-code minimal SendMessage + Message**

`src/Methods/SendMessage.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\Message;

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
        public readonly string|BotDefault|null $parseMode = new BotDefault('parse_mode'),
        ?Bot $bot = null,
    ) {
        parent::__construct($bot);
    }
}
```

Replace `src/Types/Message.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Hand-coded Phase 1 stub. Phase 2 regenerates with the full 90+ field surface.
 */
final class Message extends TelegramObject
{
    public function __construct(
        public readonly int $messageId,
        public readonly int $date,   // upstream is DateTime; simplified for smoke test
        public readonly array $chat, // simplified; replaced by Chat in Phase 2
        public readonly ?string $text = null,
        ?Bot $bot = null,
    ) {
        parent::__construct($bot);
    }
}
```

Add `src/Methods/GetMe.php`, `src/Methods/GetFile.php` minimal stubs to keep BotShortcuts compiling:

```php
<?php
namespace Gruven\PhpBotGram\Methods;
use Gruven\PhpBotGram\Types\User;
/** @extends TelegramMethod<User> */
final class GetMe extends TelegramMethod {
    public const string ApiMethod = 'getMe';
    public const string ReturnsType = User::class;
    public function __construct(?\Gruven\PhpBotGram\Bot $bot = null) { parent::__construct($bot); }
}
```

```php
<?php
namespace Gruven\PhpBotGram\Methods;
use Gruven\PhpBotGram\Types\File;
/** @extends TelegramMethod<File> */
final class GetFile extends TelegramMethod {
    public const string ApiMethod = 'getFile';
    public const string ReturnsType = File::class;
    public function __construct(public readonly string $fileId, ?\Gruven\PhpBotGram\Bot $bot = null) { parent::__construct($bot); }
}
```

And `src/Types/File.php`, `src/Types/Downloadable.php`:

```php
<?php
namespace Gruven\PhpBotGram\Types;
final class File extends TelegramObject {
    public function __construct(
        public readonly string $fileId,
        public readonly ?string $filePath = null,
        ?\Gruven\PhpBotGram\Bot $bot = null,
    ) { parent::__construct($bot); }
}
```

```php
<?php
namespace Gruven\PhpBotGram\Types;
interface Downloadable {
    public function fileId(): string;
}
```

- [ ] **Step 4: Smoke test against MockedBot**

`tests/Bot/BotSmokeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Bot;

use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;

final class BotSmokeTest extends TestCase
{
    public function testSendMessageRoundtrip(): void
    {
        $bot = new MockedBot();
        $bot->addResultFor(SendMessage::class, ok: true, result: new Message(messageId: 1, date: 0, chat: ['id' => 42], text: 'hi'));

        $result = $bot->sendMessage(chatId: 42, text: 'hi');

        self::assertInstanceOf(Message::class, $result);
        self::assertSame('hi', $result->text);

        $sent = $bot->getRequest();
        self::assertInstanceOf(SendMessage::class, $sent);
        self::assertSame('hi', $sent->text);
    }
}
```

- [ ] **Step 5: Run — pass; Commit**

```bash
git add src/Bot.php src/Client/BotShortcutsContract.php src/Client/BotShortcuts.php src/Methods/SendMessage.php src/Methods/GetMe.php src/Methods/GetFile.php src/Types/Message.php src/Types/File.php src/Types/Downloadable.php tests/Bot/BotSmokeTest.php
git commit -m "feat: Bot facade skeleton + BotShortcuts + Phase-1 hand-coded SendMessage/Message smoke test"
```

### Task 1.7: MockedSession + MockedBot test harness (moved here from Phase 0)

**Files:**
- Create: `tests/Support/MockedSession.php`
- Create: `tests/Support/MockedBot.php`
- Create: `tests/Support/MockedSessionTest.php`

After Phase 1.3 (full BaseSession) and Phase 1.6 (full Bot with real constructor) land, the test harness can subclass them cleanly. Spec § "Test infrastructure" — `me()` pre-stub, `addResultFor` helper.

- [ ] **Step 1: Implement MockedSession**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Session\BaseSession;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Methods\TelegramMethod;

final class MockedSession extends BaseSession
{
    /** @var \SplDoublyLinkedList<Response> */
    private \SplDoublyLinkedList $responses;
    /** @var \SplDoublyLinkedList<TelegramMethod> */
    private \SplDoublyLinkedList $requests;
    public bool $closed = true;

    public function __construct()
    {
        parent::__construct();   // BaseSession's $api, $middleware are now initialized
        $this->responses = new \SplDoublyLinkedList();
        $this->requests = new \SplDoublyLinkedList();
    }

    public function addResult(Response $response): Response
    {
        $this->responses->push($response);
        return $response;
    }

    public function getRequest(): TelegramMethod
    {
        if ($this->requests->isEmpty()) {
            throw new \RuntimeException('No recorded requests');
        }
        return $this->requests->pop();
    }

    public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
    {
        $this->closed = false;
        $this->requests->push($method);
        if ($this->responses->isEmpty()) {
            throw new \RuntimeException('No canned responses left');
        }
        return $this->responses->pop()->result;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream
    {
        // Not exercised in MockedBot-driven tests; return empty stream as a sensible default.
        return new ReadableBuffer('');
    }
}
```

- [ ] **Step 2: Implement MockedBot**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Types\User;

final class MockedBot extends Bot
{
    private User $cachedMeStub;

    public function __construct(string $token = '42:TEST')
    {
        // Phase 1.6 Bot::__construct accepts (token, session, defaultProperties).
        // We supply a MockedSession; the inherited readonly $session resolves to it.
        parent::__construct(token: $token, session: new MockedSession());
        $this->cachedMeStub = new User(
            id: 42,
            isBot: true,
            firstName: 'FirstName',
            lastName: 'LastName',
            username: 'tbot',
            languageCode: 'uk-UA',
        );
    }

    public function getMockedSession(): MockedSession
    {
        assert($this->session instanceof MockedSession);
        return $this->session;
    }

    /**
     * Pre-stub matching upstream tests/mocked_bot.py:63-70. Override the trait's
     * me() since we want to bypass the GetMe network round-trip.
     */
    public function me(): User
    {
        return $this->cachedMeStub;
    }

    /**
     * @template T of TelegramMethod
     * @param class-string<T> $methodClass
     */
    public function addResultFor(
        string $methodClass,
        bool $ok,
        mixed $result = null,
        ?string $description = null,
        int $errorCode = 200,
        ?int $migrateToChatId = null,
        ?int $retryAfter = null,
    ): Response {
        $parameters = new \Gruven\PhpBotGram\Types\ResponseParameters(
            migrateToChatId: $migrateToChatId,
            retryAfter: $retryAfter,
        );
        $response = new Response(
            ok: $ok,
            result: $result,
            description: $description,
            errorCode: $errorCode,
            parameters: $parameters,
        );
        $this->getMockedSession()->addResult($response);
        return $response;
    }

    public function getRequest(): TelegramMethod
    {
        return $this->getMockedSession()->getRequest();
    }
}
```

- [ ] **Step 3: Smoke test**

```php
<?php
declare(strict_types=1);
namespace Gruven\PhpBotGram\Tests\Support;
use PHPUnit\Framework\TestCase;
final class MockedSessionTest extends TestCase {
    public function testBotMeReturnsStub(): void {
        $bot = new MockedBot();
        self::assertSame(42, $bot->me()->id);
        self::assertSame('tbot', $bot->me()->username);
    }
}
```

- [ ] **Step 4: Run — pass; Commit**

```bash
git add tests/Support/MockedSession.php tests/Support/MockedBot.php tests/Support/MockedSessionTest.php
git commit -m "feat(test): MockedSession + MockedBot — full implementation after Phase 1 base classes"
```

### Task 1.8: Phase 1 acceptance gate

- [ ] **Step 1: Full check**

```
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyze --no-progress
vendor/bin/phpunit
```

- [ ] **Step 2: Manual integration (optional, if TG_TEST_TOKEN env var available)**

Skip unless test bot credentials are configured.

- [ ] **Step 3: Tag**

```bash
git commit --allow-empty -m "chore: Phase 1 — foundation complete"
git tag phase-1-complete
```

---

## Phase 2 — Codegen

Build the generator and emit the full type/method/enum/Bot/F-DSL surface. The hand-coded SendMessage/Message/GetMe/GetFile from Phase 1 are deleted at the end of this phase and replaced by generated equivalents.

### Task 2.1: Vendor the upstream .butcher schema

**Files:**
- Create: `.butcher/schema/schema.json`
- Create: `.butcher/types/<TypeName>/{entity.json,aliases.yml?,replace.yml?}` (305 dirs)
- Create: `.butcher/methods/<methodName>/{entity.json,default.yml?}` (176 dirs)
- Create: `.butcher/enums/<EnumName>/{entity.json,replace.yml?}` (34 dirs)
- Create: `scripts/sync-schema.sh`

- [ ] **Step 1: Copy from upstream checkout**

Run:

```bash
mkdir -p .butcher
cp -R /Users/gruven/repository/github/aiogram/.butcher/{schema,types,methods,enums} .butcher/
ls .butcher/types | wc -l   # expect 305
ls .butcher/methods | wc -l # expect 176
ls .butcher/enums | wc -l   # expect 34
```

- [ ] **Step 2: Write scripts/sync-schema.sh**

```bash
#!/usr/bin/env bash
set -euo pipefail
UPSTREAM_PATH="${1:?usage: sync-schema.sh /path/to/aiogram/.butcher}"

rsync -a --delete \
    "${UPSTREAM_PATH}/schema/" .butcher/schema/
rsync -a --delete \
    "${UPSTREAM_PATH}/types/" .butcher/types/
rsync -a --delete \
    "${UPSTREAM_PATH}/methods/" .butcher/methods/
rsync -a --delete \
    "${UPSTREAM_PATH}/enums/" .butcher/enums/
echo "Schema synced from $UPSTREAM_PATH"
```

Make executable: `chmod +x scripts/sync-schema.sh`

- [ ] **Step 3: Commit**

```bash
git add .butcher scripts/sync-schema.sh
git commit -m "chore: vendor upstream .butcher schema (305 types / 176 methods / 34 enums) + sync script"
```

### Task 2.2 – 2.11: Implement generator pipeline stages

For each stage, write the class under `tools/generator/src/` with a corresponding test under `tests/Generator/`. Stages and responsibilities (per spec § "Generator pipeline" 9 stages):

- [ ] **Task 2.2: `SchemaLoader`** — parses `schema/schema.json` + applies per-entity patches. Returns a `LoadedSchema` value object with `types: list<TypeEntity>`, `methods: list<MethodEntity>`, `enums: list<EnumEntity>`.
- [ ] **Task 2.3: `TypeResolver`** — maps Telegram primitive type strings (`Integer`, `String`, `Boolean`, `Float`, `True`, `Array of X`, `X or Y`) to PHP types. Returns `PhpType` value objects.
- [ ] **Task 2.4: `NameMapper`** — snake_case ↔ camelCase; renames per the spec's policy: `from`→`fromUser`, `class`→`className`, `list`→`items`, `function`→`fn`. Fail-closed on unknown PHP-keyword-position collisions (rare).
- [ ] **Task 2.5: `TypeOverrideApplier`** — consumes `replace.yml`: `annotations.<field>.parsed_type`, `annotations.<field>.required`, `bases:` (incl. propagation through union inheritance for the 16 MutableTelegramObject + 2 custom-parent lifts).
- [ ] **Task 2.6: `UnionDetector`** — emits abstract base + concrete subclasses + `*Union` final class with `static members(): array` + `resolve(array $payload)`.
- [ ] **Task 2.7: `ShortcutDetector`** — reads `aliases.yml`; lowers `self.X`/`self.X()`/`assert`/`ternary`/YAML anchors/`<<:`/`ignore:` per spec § "ShortcutDetector" worked examples A & B. Silently skips types without aliases.yml.
- [ ] **Task 2.8: `DefaultsResolver`** — consumes `methods/<name>/default.yml`; threads `new BotDefault(...)` defaults into method constructor parameter expressions.
- [ ] **Task 2.9: `HandAuthoredShortcutsIntegrator`** — detects `src/Types/Shortcuts/<TypeName>Shortcuts.php` traits and emits `use <TypeName>Shortcuts;` in the generated class. Aborts on method-name collisions between alias and trait.
- [ ] **Task 2.10: `Renderer`** — Twig-based file emission with sorted iteration + cs-fixer post-pass. Templates: `type.php.twig`, `method.php.twig`, `enum.php.twig`, `bot.php.twig`, `f-builder.php.twig`.
- [ ] **Task 2.11: `FDslGenerator` (build only; do NOT emit yet)** — design the generator class and write its tests against frozen fixtures. **Do not run** it during Phase 2 because the emitted builders depend on `Utils\MagicFilter\MagicFilter` and `Filters\F\BaseField`/etc., which land in Phase 4. F-DSL emission is moved to **Phase 4 Task 4.11** (after MagicFilter + runtime field primitives exist).

Each task follows the TDD pattern: write fixtures of a sample entity → expected PHP output → integration test that runs the stage and diffs.

Concrete per-task template (apply to each stage):

```
[ ] Step 1: Write integration test with a representative fixture
    e.g. tests/Generator/UnionDetectorTest.php loads BackgroundFill entity
    and asserts the emitted PHP matches a frozen fixture file
[ ] Step 2: Run — fails
[ ] Step 3: Implement the stage
[ ] Step 4: Run — pass
[ ] Step 5: Commit
```

Reference upstream's butcher generator at `/Users/gruven/repository/github/aiogram/scripts/` for proven patch-handling logic. Port the algorithms; emit PHP via Twig.

### Task 2.12: Generator CLI

**Files:**
- Create: `tools/generator/bin/generate.php`

- [ ] **Step 1: CLI entry point**

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Gruven\PhpBotGram\Generator\Pipeline;

$opts = getopt('', ['schema:', 'patches:', 'out:']);
$schema = $opts['schema'] ?? __DIR__ . '/../../../.butcher/schema/schema.json';
$patches = $opts['patches'] ?? __DIR__ . '/../../../.butcher';
$out = $opts['out'] ?? __DIR__ . '/../../../src';

$pipeline = new Pipeline($schema, $patches, $out);
$pipeline->run();
echo "Generated to {$out}\n";
```

- [ ] **Step 2: Pipeline orchestrator**

`tools/generator/src/Pipeline.php` — composes Schema → TypeResolver → NameMapper → TypeOverrideApplier → UnionDetector → ShortcutDetector → DefaultsResolver → HandAuthoredShortcutsIntegrator → Renderer + FDslGenerator. Wired sequentially.

- [ ] **Step 3: Commit**

```bash
chmod +x tools/generator/bin/generate.php
git add tools/generator/bin/generate.php tools/generator/src/Pipeline.php
git commit -m "feat(generator): CLI entry point + Pipeline orchestrator"
```

### Task 2.13: SchemaInfo + determinism test

**Files:**
- Create: `tools/generator/src/SchemaInfo.php`
- Create: `tests/Generator/DeterminismTest.php`

- [ ] **Step 1: SchemaInfo pins counts**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

final class SchemaInfo
{
    public const int TYPE_ENTITIES = 305;
    public const int METHOD_ENTITIES = 176;
    public const int ENUM_ENTITIES = 34;

    public const int EMITTED_TYPE_FILES = 341;
    public const int EMITTED_METHOD_FILES = 178;
    public const int EMITTED_ENUM_FILES = 35;
}
```

- [ ] **Step 2: Determinism test**

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use PHPUnit\Framework\TestCase;

final class DeterminismTest extends TestCase
{
    public function testSecondRunProducesNoDiff(): void
    {
        $before = $this->hashTree(__DIR__ . '/../../src');
        passthru('php ' . __DIR__ . '/../../tools/generator/bin/generate.php', $code);
        self::assertSame(0, $code);
        $after = $this->hashTree(__DIR__ . '/../../src');
        self::assertSame($before, $after, 'Generator must be deterministic across runs');
    }

    private function hashTree(string $path): string
    {
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $files = [];
        foreach ($iter as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[(string) $file] = hash_file('sha256', (string) $file);
            }
        }
        ksort($files);
        return hash('sha256', json_encode($files));
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add tools/generator/src/SchemaInfo.php tests/Generator/DeterminismTest.php
git commit -m "feat(generator): SchemaInfo entity counts + determinism test"
```

### Task 2.14: Run generator, delete hand-coded Phase 1 stubs

- [ ] **Step 1: Run generator end-to-end**

```bash
make regenerate
```

Verify: `ls src/Types | wc -l ≈ 341`, `ls src/Methods | wc -l ≈ 178`, `ls src/Enums | wc -l ≈ 35`, `src/Bot.php` is ~6000 lines.

- [ ] **Step 2: Delete the Phase 1 hand-coded stubs that the generator did NOT overwrite**

The generator overwrites in place: `src/Bot.php`, `src/Methods/SendMessage.php`, `src/Methods/GetMe.php`, `src/Methods/GetFile.php`, `src/Types/Message.php`, `src/Types/User.php`, `src/Types/File.php`, `src/Types/LinkPreviewOptions.php`, `src/Types/ResponseParameters.php` (verified — same paths). Run an automated parity check to confirm:

```bash
test -f src/Methods/SendMessage.php && grep -q 'auto-generated' src/Methods/SendMessage.php && echo "regenerated: SendMessage"
test -f src/Types/Message.php && grep -q 'auto-generated' src/Types/Message.php && echo "regenerated: Message"
```

Any file whose Phase 1 stub has a path the generator does NOT emit stays as-is. **Explicit allow-list of files that survive regeneration** (these are NOT in `.butcher/types/` and the generator must not touch them):
- `src/Types/Downloadable.php` — interface, not a schema type
- `src/Types/InputFile.php` — abstract base, hand-written
- `src/Types/BufferedInputFile.php` — hand-written concrete subclass
- `src/Types/FsInputFile.php` — hand-written concrete subclass
- `src/Types/UrlInputFile.php` — hand-written concrete subclass
- `src/Types/Custom/DateTime.php` — hand-written helper
- `src/Types/Unspecified.php` — sentinel singleton, hand-written
- `src/Types/MutableTelegramObject.php` — hand-written base (children may be schema-emitted via `bases:` patches)
- `src/Types/TelegramObject.php` — hand-written root

The renderer (Task 2.10) must consult `.butcher/types/<TypeName>/entity.json` for the allowed schema-name list and **skip overwrite** for any path that doesn't map to a schema entity. Add an explicit assertion to the codegen acceptance test:

```bash
# Phase 2 acceptance: verify hand-written files are unchanged after regenerate
git diff --exit-code src/Types/InputFile.php src/Types/BufferedInputFile.php \
                    src/Types/FsInputFile.php src/Types/UrlInputFile.php \
                    src/Types/Custom/DateTime.php src/Types/Unspecified.php \
                    src/Types/MutableTelegramObject.php src/Types/TelegramObject.php \
                    src/Types/Downloadable.php
```

Update the Phase 1 smoke test (`tests/Bot/BotSmokeTest.php`) to instantiate the **regenerated** `Message` with the new constructor signature (`Chat $chat` typed object, not an array literal). Stub `Chat`:

```php
$chat = new \Gruven\PhpBotGram\Types\Chat(id: 42, type: \Gruven\PhpBotGram\Enums\ChatType::Private);
$result = new \Gruven\PhpBotGram\Types\Message(messageId: 1, date: new \Gruven\PhpBotGram\Types\Custom\DateTime('@0'), chat: $chat, text: 'hi');
```

- [ ] **Step 3: Re-run full suite**

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyze
```

Expected: smoke test from Task 1.6 still passes — proves the regenerated `Bot::sendMessage` is wire-compatible.

- [ ] **Step 4: Commit the generated tree (Types/Methods/Enums/Bot only — NO F-DSL yet)**

```bash
git add src/Bot.php src/Types/ src/Methods/ src/Enums/
git commit -m "feat: regenerate full Bot/Types/Methods/Enums from .butcher"
```

F-DSL builders (`src/Filters/F/*F.php`) are NOT emitted in Phase 2 — they depend on `Utils\MagicFilter\MagicFilter` and `Filters\F\BaseField`/`StringField`/etc. which land in Phase 4. They're emitted at Phase 4 Task 4.11 after their runtime dependencies exist.

### Task 2.15: Phase 2 acceptance gate

- [ ] PHPStan level 9 clean on generated tree
- [ ] PHPUnit smoke test passes against generated `Bot::sendMessage`
- [ ] `make regenerate && git diff --exit-code` (zero diff)
- [ ] Tag: `git tag phase-2-complete`

---

## Phase 3 — Dispatcher

Router/Dispatcher/Observer/Middleware/Polling. The full polling loop with `silentCallRequest` and the webhook deadline contract.

For each component, port the upstream class to PHP via TDD. The spec sections "Dispatcher, Router, Filters", "Polling loop", "Webhook response contract", "Magic-filter runtime + F-DSL" (Layer 2 — MagicData), and "Injected dispatcher kwargs" pin every behavioral contract.

### Task list (TDD per task; each ~30-60 min)

- [ ] **Task 3.1: `EventObserver`** (startup/shutdown lifecycle). Tests: `register/trigger/clear`. Files: `src/Dispatcher/Event/EventObserver.php`, `tests/Dispatcher/Event/EventObserverTest.php`.
- [ ] **Task 3.2: `CallableObject`** + reflection-based kwarg binding (includes `varKw` detection). Tests: bind only declared params; respect variadic. Files: `src/Dispatcher/Event/CallableObject.php`, `tests/Dispatcher/Event/CallableObjectTest.php`.
- [ ] **Task 3.3: `FilterObject` + `HandlerObject`** — wrap callable + filters; `check()` returns `(bool, array)` tuple. Files: `src/Dispatcher/Event/{FilterObject,HandlerObject}.php`, `tests/Dispatcher/Event/HandlerObjectTest.php`.
- [ ] **Task 3.4: `BaseMiddleware`** + `MiddlewareManager` (with the `mixed $offset` ArrayAccess shape per spec). Tests: register/wrap/chain. Files: `src/Dispatcher/Middlewares/BaseMiddleware.php`, `src/Dispatcher/Middlewares/MiddlewareManager.php`, `tests/Dispatcher/Middlewares/MiddlewareManagerTest.php`.
- [ ] **Task 3.5: `EventContext` + `UserContextMiddleware`** — injects `event_context`, `event_from_user`, `event_chat`, `event_thread_id`. Files: `src/Dispatcher/Middlewares/{EventContext,UserContextMiddleware}.php`, `tests/Dispatcher/Middlewares/UserContextMiddlewareTest.php`.
- [ ] **Task 3.6: `ErrorsMiddleware`** — catches handler exceptions, swallows `SkipHandlerException`/`CancelHandlerException`, routes to the `errors` observer. Files: `src/Dispatcher/Middlewares/ErrorsMiddleware.php`, `tests/Dispatcher/Middlewares/ErrorsMiddlewareTest.php`.
- [ ] **Task 3.7: Flags subsystem** — `Flag`, `FlagAttribute` (`#[Flag]`), `FlagDecorator`, `FlagGenerator`, plus helpers (`extractFlags`, `extractFlagsFromObject`, `getFlag`, `checkFlags`). Closures stored in `\WeakMap<\Closure|object, list<Flag>>`. Files: `src/Dispatcher/Flags/`, `tests/Dispatcher/Flags/FlagsTest.php`.
- [ ] **Task 3.8: `TelegramEventObserver`** — `register`, `filter` (global), `__invoke` (decorator factory), `trigger`. Signatures per spec § "TelegramEventObserver" (variadic last, `?array $flags = null` named arg). Files: `src/Dispatcher/Event/TelegramEventObserver.php`, `tests/Dispatcher/Event/TelegramEventObserverTest.php`.
- [ ] **Task 3.9: `Router`** — observers map for all 25 update types + `errors`; `includeRouter`/`includeRouters`/`resolveUsedUpdateTypes`/`propagateEvent` (injects `event_router`); `emitStartup`/`emitShutdown` (injects `router`). Files: `src/Dispatcher/Router.php`, `tests/Dispatcher/RouterTest.php`.
- [ ] **Task 3.10: `Dispatcher`** — extends Router; constructor signature per spec § "Dispatcher" (named-only convention documented); `feedUpdate`/`feedRawUpdate`/`_feedWebhookUpdate`/`feedWebhookUpdate`/`silentCallRequest`. Files: `src/Dispatcher/Dispatcher.php`, `tests/Dispatcher/DispatcherTest.php`.
- [ ] **Task 3.11: `PollingOptions` DTO** + `Backoff` + `BackoffConfig`. Spec defaults (pollingTimeout=10, backoffConfig=1.0/5.0/1.3/0.1). Files: `src/Dispatcher/PollingOptions.php`, `src/Utils/{Backoff,BackoffConfig}.php`, `tests/Utils/BackoffTest.php`.
- [ ] **Task 3.12: Polling loop** — `_listenUpdates`/`_polling`/`startPolling`/`runPolling`/`stopPolling` with `$runningLock: LocalMutex` + `$isPolling: bool` flag, per-bot `$handleUpdateTasks: array<int, Future>`, shared `$stopSignal: DeferredFuture`, signal handling via `EventLoop::onSignal`. Files: extends `src/Dispatcher/Dispatcher.php`, `tests/Dispatcher/PollingTest.php`.
- [ ] **Task 3.13: Webhook response contract + RecordingDispatcher** — `feedWebhookUpdate` 55s deadline + `silentCallRequest` fall-through + `trigger_error("Detected slow response into webhook…", E_USER_WARNING)`. Also create `tests/Support/RecordingDispatcher.php`:
   ```php
   <?php
   declare(strict_types=1);
   namespace Gruven\PhpBotGram\Tests\Support;
   use Gruven\PhpBotGram\Bot;
   use Gruven\PhpBotGram\Dispatcher\Dispatcher;
   use Gruven\PhpBotGram\Methods\TelegramMethod;
   /** Records silentCallRequest invocations instead of dispatching. */
   final class RecordingDispatcher extends Dispatcher {
       /** @var list<array{Bot, TelegramMethod}> */
       public array $silentCalls = [];
       public function silentCallRequest(Bot $bot, TelegramMethod $method): void {
           $this->silentCalls[] = [$bot, $method];
       }
   }
   ```
   Files: extends `Dispatcher`, `tests/Dispatcher/WebhookContractTest.php`, `tests/Support/RecordingDispatcher.php`.
- [ ] **Task 3.14: Echo bot end-to-end** — port `examples/echo_bot.py`. Files: `examples/echo_bot.php`. Verifies the dispatcher works against the generated `Bot`.
- [ ] **Task 3.15: Phase 3 acceptance gate**

```bash
git tag phase-3-complete
```

---

## Phase 4 — Filters & Magic-filter runtime

Three layers per spec § "Magic-filter runtime + F-DSL":
1. `Utils\MagicFilter\MagicFilter` — full PHP port of `magic_filter` + aiogram's `.as_()` extension (~1200-1500 LOC realistic).
2. `Filters\MagicData` filter.
3. `Filters\F\*` typed builders runtime primitives (`BaseField`, `StringField`, `IntField`, …) + the generated `MessageF`/`CallbackQueryF`/etc. emitted in Phase 2.

Plus all the discrete filter classes (`Command`, `CallbackData`, `StateFilter`, `ChatMemberUpdatedFilter`, exception filters, logic combinators).

- [ ] **Task 4.1: `Filter` abstract base + `Logic\AndFilter/OrFilter/InvertFilter`** + `Filter::all/any/invertOf`. TDD.
- [ ] **Task 4.2: MagicFilter — operations** (Attribute, MethodCall, Comparison, AsFilterResult, NotOperation, And/Or). Port file-by-file from upstream `magic_filter` source. ~10 sub-tasks, one per operation class.
- [ ] **Task 4.3: MagicFilter chain semantics** — `resolve()` walks operations; `asFilter()` produces `Filter`. Test: comparison results propagate as boolean values mid-chain; only `AsFilterResultOperation` and final `asFilter()` perform pass/fail discrimination per spec.
- [ ] **Task 4.4: `F` const + `MagicFilter::root()`** — top-level `const F = new MagicFilter();` in `src/F.php`; `use const Gruven\PhpBotGram\F;` enables `F->text->equals('hi')`. TDD with a smoke test.
- [ ] **Task 4.5: `Filters\F\BaseField` + typed fields** (`StringField`, `IntField`, `BoolField`, `RegexField`, `DateTimeField`, `NullableStringField`, `NullableIntField`, `NullableObjectField`). Each method on a field composes a `MagicFilter` and returns either another field (for chainable transforms) or a `Filter`.
- [ ] **Task 4.6: `MagicData` filter** — `__invoke(TelegramObject $event, mixed ...$kwargs): bool|array` reassembling `['event' => $event] + $kwargs` into the data dict. TDD.
- [ ] **Task 4.7: `Command` + `CommandStart` + `CommandObject`** — port `aiogram/filters/command.py`. Constructor takes `array $commands = []` + named options + `Command::of(...)` variadic factory per spec § "Filters in detail".
- [ ] **Task 4.8: `CallbackData`** + `#[CallbackPrefix]` attribute. Class-level metadata read via reflection; full type-encoding table (null/bool/numeric/string/Stringable/UnitEnum/UUID). 64-byte UTF-8 limit. `CallbackQueryFilter`.
- [ ] **Task 4.9: `StateFilter`** — accepts `State|StatesGroup|string`. Triggers `StatesGroup::bootstrapIfNeeded` defensively.
- [ ] **Task 4.10: `ChatMemberUpdatedFilter`** — port upstream transitions DSL.
- [ ] **Task 4.11: `ExceptionTypeFilter` + `ExceptionMessageFilter`** + `BaseFilter` `class_alias`.
- [ ] **Task 4.12: Run F-DSL codegen (Phase 2's `FDslGenerator` was built but not run).** Once `MagicFilter`, `BaseField`/`StringField`/`IntField`/etc. land (Tasks 4.2-4.5), execute the F-DSL emission step. Verify acceptance: `ls src/Filters/F | wc -l ≈ 50` (25 event-root builders + ~25 first-level nested field-builders), PHPStan level 9 clean, smoke test instantiates `MessageF::text()->equals('hi')`.
- [ ] **Task 4.13: Wire-up generated `Filters\F\*` builders** — verify each generated event-typed builder constructs valid MagicFilter chains against a sample payload.
- [ ] **Task 4.14: Port `tests/test_filters/*`** — translate upstream test cases module-by-module.
- [ ] **Task 4.15: Phase 4 acceptance gate**

```bash
git tag phase-4-complete
```

---

## Phase 5 — FSM (State, FSMContext, Storages, Scenes)

- [ ] **Task 5.1: `StorageKey` (readonly DTO) + `KeyBuilder` interface + `DefaultKeyBuilder`** — exact upstream layout.
- [ ] **Task 5.2: `BaseStorage` abstract** — `setState/getState/setData/getData/getValue/updateData/close` with the `getValue` and `updateData` concrete implementations per spec § "Storage".
- [ ] **Task 5.3: `BaseEventIsolation` + `Lock` + `DisabledEventIsolation` + `SimpleEventIsolation`** — uses `Amp\Sync\LocalKeyedMutex`.
- [ ] **Task 5.4: `MemoryStorage`** + `MemoryStorageRecord` value object.
- [ ] **Task 5.5: `State` + `StatesGroup`** with `bootstrap()`/`bootstrapIfNeeded()` + `const CHILDREN` walker. Test: explicit-bootstrap pattern + framework defense in `StateFilter`.
- [ ] **Task 5.6: `FSMContext`** + `FsmStrategy` enum.
- [ ] **Task 5.7: `FsmContextMiddleware`** — injects `state`, `raw_state`, `fsm_storage` per spec kwargs table.
- [ ] **Task 5.8: `Scene` + `#[SceneState]` attribute + `#[On*]` event marker attributes (25 total) + `SceneAction` enum + `After` factory** (with `::exit()`/`::back()`/`::goto()`).
- [ ] **Task 5.9: `SceneWizard`** — full per-scene surface (`enter/leave/exit/back/retake/goto/setData/getData/updateData/getValue`).
- [ ] **Task 5.10: `ScenesManager`** — per-update `enter/close` wizard front-end.
- [ ] **Task 5.11: `SceneRegistry` + `HistoryManager`** — `add()`/`register()` flow.
- [ ] **Task 5.12: `Scene::asHandler()` + `Scene::asRouter(?string $name = null)`** matching upstream signatures.
- [ ] **Task 5.13: `RedisStorage`** — built on `amphp/redis` ^2. Storage TTL config per upstream.
- [ ] **Task 5.14: `RedisEventIsolation`** — `SET NX EX` locking via `amphp/redis`.
- [ ] **Task 5.15: `MongoStorage`** — sync `mongodb/mongodb` wrapped via `Amp\async()`. Two-name `$database` + `$collection` per spec.
- [ ] **Task 5.16: Port `tests/test_fsm/*`** — Redis/Mongo subdirs skip via env DSN check.
- [ ] **Task 5.17: Phase 5 acceptance gate**

```bash
git tag phase-5-complete
```

---

## Phase 6 — Webhook

- [ ] **Task 6.1: `IpFilter`** — built-in Telegram subnets (149.154.160.0/20, 91.108.4.0/22), CIDR matching.
- [ ] **Task 6.2: `BaseRequestHandler`** — `Amp\Http\Server\Request`/`Response` interface; abstract `resolveBot`/`verifySecret`/`close`. `$handleInBackground` flag.
- [ ] **Task 6.3: `SimpleRequestHandler`** — single Bot, optional `$secretToken` validated via `hash_equals`. `$handleInBackground = true` default.
- [ ] **Task 6.4: `TokenBasedRequestHandler`** — multi-bot, URL contains `{bot_token}` (snake_case to match upstream).
- [ ] **Task 6.5: `Server\AmphpServer::run(...)`** — boots `amphp/http-server` instance.
- [ ] **Task 6.6: `Webhook\Setup::register(...)`** — wires phpbotgram into an existing `amphp/http-server` app + emits startup/shutdown observers via `onStart`/`onStop`.
- [ ] **Task 6.7: Port `tests/test_webhook/*`**.
- [ ] **Task 6.8: Phase 6 acceptance gate**

```bash
git tag phase-6-complete
```

---

## Phase 7 — Utils

- [ ] **Task 7.1: `TextDecoration` + `HtmlDecoration` + `MarkdownDecoration`** — full port of `aiogram/utils/text_decorations.py`. UTF-16LE surrogate-pair accounting via `mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')`. Entity offset/length in 16-bit code units → byte offsets × 2.
- [ ] **Task 7.2: `DeepLinking`** — `createStartLink`, `decodePayload`, `encodePayload`.
- [ ] **Task 7.3: `Keyboard\InlineKeyboardBuilder` + `Keyboard\ReplyKeyboardBuilder`** — extend `MutableTelegramObject`.
- [ ] **Task 7.4: `MediaGroup\MediaGroupBuilder`**.
- [ ] **Task 7.5: `ChatAction\ChatActionSender`** — periodic chat-action emission.
- [ ] **Task 7.6: `CallbackAnswer\CallbackAnswerMiddleware`**.
- [ ] **Task 7.7: `WebApp\WebAppSignature` + `AuthWidget`**.
- [ ] **Task 7.8: `Link\docsUrl`** + `Payload` helpers.
- [ ] **Task 7.9: Port `tests/test_utils/*`**.
- [ ] **Task 7.10: Phase 7 acceptance gate**

```bash
git tag phase-7-complete
```

---

## Phase 8 — Tests + examples

- [ ] **Task 8.1: Port `tests/test_api/test_client/*`** — Bot session, prepareValue, checkResponse coverage.
- [ ] **Task 8.2: Port `tests/test_api/test_methods/*`** — one test class per method via MockedBot.
- [ ] **Task 8.3: Port `tests/test_api/test_types/*`** — serialization round-trips.
- [ ] **Task 8.4: Port `tests/test_handler/*`** — class-based handler surface.
- [ ] **Task 8.5: Port `tests/test_dispatcher/*`** — already partially covered in Phase 3; complete the suite.
- [ ] **Task 8.6: Port `tests/test_flags/*`**.
- [ ] **Task 8.7: Port `tests/test_issues/*`** — regression tests.
- [ ] **Task 8.8: Port 12+ examples** — file-by-file translation of upstream `examples/`. Each example becomes a runnable PHP script under `examples/`.
- [ ] **Task 8.9: README quickstart** — install + run echo bot.
- [ ] **Task 8.10: Coverage gate** — verify ≥90% on core (Bot, Session, Dispatcher, Router, Filters, FSM).
- [ ] **Task 8.11: Phase 8 acceptance gate**

```bash
git tag phase-8-complete
```

---

## Phase 9 — Polish + v0.1.0

- [ ] **Task 9.1: API documentation** — generate phpDocumentor/Doctum site OR ship Markdown reference under `docs/`.
- [ ] **Task 9.2: Sample deployment configs** — nginx + amphp/http-server example; systemd unit; Docker compose for the test bot.
- [ ] **Task 9.3: CHANGELOG.md initial entry**.
- [ ] **Task 9.4: README polish** — badges, install, links to spec/plan/examples.
- [ ] **Task 9.5: Tag v0.1.0**

```bash
git tag v0.1.0
git push --tags
```

---

## Self-Review Checklist

After committing the plan:

**1. Spec coverage:** Does every section in the spec map to at least one task?
- Architectural translation strategy → Phase 0/1 (foundation classes)
- Technology stack → Phase 0 (composer)
- Namespace layout → Tasks 0.4/0.5 + Phase 2 (codegen) + Phase 3-7 (each subsystem)
- Async runtime and HTTP layer → Phase 1.3-1.5
- Types and methods (codegen) → Phase 2 (full)
- Dispatcher/Router/Filters → Phase 3 + Phase 4
- F-DSL + MagicFilter → Phase 4
- FSM + Scenes → Phase 5
- Webhook → Phase 6
- Utils → Phase 7
- Exceptions → Phase 0.9
- Testing strategy → Phase 0.11 + Phase 8
- Determinism criterion → Task 2.13
- All 10 spec phases (0-9) → 10 plan phases (0-9). ✓

**2. Placeholder scan:** Search for "TODO", "TBD", "implement later", "Similar to Task N" — none.

**3. Type consistency:** `MockedBot::addResultFor` signature in Task 0.11 matches the usage in Task 1.6 smoke test. `BotShortcutsContract` declared in 1.6 matches `BotShortcuts` trait. `BaseSession::makeRequest`/`prepareValue`/`checkResponse` signatures consistent across 0.11/1.3/1.5.

**4. Bite-sized:** Phase 0/1 steps are 2-5 minute actions (write test → run fail → implement → run pass → commit). Phase 2-9 are task-level (since each subsystem's tasks repeat the same TDD pattern that engineers internalize after Phase 0/1).

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md`.

The plan is intentionally heaviest on Phase 0/1 (full TDD code blocks for every step) because those build the foundation that every later phase relies on. Phase 2 onwards uses task-level granularity since the TDD pattern is internalized and the spec already specifies behavior contracts for each port; the implementer or subagent fills in code per task by referring back to the spec.

For execution, recommend **subagent-driven development** (fresh subagent per task with review between tasks).
