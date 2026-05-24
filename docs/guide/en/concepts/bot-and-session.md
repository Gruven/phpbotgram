# Bot and Session

The `Bot` facade is the thin entry point users hold; the `Session` is
the HTTP transport that turns a typed method DTO into a wire call.

## How it works

### Constructing a Bot and making calls

[`Bot`](https://api.phpbotgram.local/Gruven-PhpBotGram-Bot.html) is a
codegen-produced facade with one typed method per Telegram Bot API
endpoint (`sendMessage`, `getChat`, `editMessageText`, … plus the
managed-bot extensions). Each method allocates a small DTO from
`Methods/` and forwards it to the session via `$bot($method)`. The
DTO carries no I/O logic — it is a pure value object with a
`::ApiMethod` class constant naming the wire endpoint and a
`ReturnsType` PHPDoc anchor that types the response. The codegen runs
during `make regenerate` and writes both the `Bot.php` facade and the
`Methods/*.php` shape classes, so a Bot API schema bump is one
regenerate cycle away from a typed PHP surface.

The simplest usage is constructing a `Bot` from a token and handing it
to a `Dispatcher`:

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

$bot = new Bot(getenv('BOT_TOKEN') ?: '123:abc');
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

When you need raw access without a dispatcher — for scripts, probes, or
migrations — call `$bot(new MethodDTO(...))` directly:

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Methods\SendMessage;

$bot = new Bot(getenv('BOT_TOKEN') ?: '123:abc');
$offset = null;

while (true) {
    $updates = $bot(new GetUpdates(offset: $offset, timeout: 10));

    foreach ($updates as $update) {
        $offset = $update->updateId + 1;
        $message = $update->message;
        if ($message === null || $message->text === null) {
            continue;
        }
        $bot(new SendMessage(
            chatId: $message->chat->id,
            text: "You said: {$message->text}",
        ));
    }
}
```

### The session layer

The default session is
[`AmphpSession`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-AmphpSession.html),
which inherits from
[`BaseSession`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-BaseSession.html).
`BaseSession` owns the middleware chain (request middlewares run
around `makeRequest`), JSON encoder/decoder injection, the
`TelegramApiServer` host configuration, and the
`prepareValue` / `checkResponse` serialization seams. The concrete
amphp implementation only contributes the actual HTTP call —
`amphp/http-client` v5 with form-urlencoded bodies. The bot
constructor accepts a custom session by argument, so testing
substitutes a recording session and production sites with a local
Bot API server can point at a self-hosted host through
`TelegramApiServer::fromBase('http://localhost:8081', isLocal: true)`.

### The `$event->answer(...)` shortcut

The `Bot::setCurrent` FiberLocal is what makes `$message->answer($text)`
work without the caller threading a `$bot` parameter through every
handler. The dispatcher binds the current bot at `feedUpdate` entry and
clears the slot in a `finally` block, so a handler exception never
leaves a stale binding. Nested `TelegramObject`s also carry an explicit
`?Bot $bot` constructor parameter — the serializer threads the bot when
hydrating the `Update`, so shortcuts on nested types (e.g.
`CallbackQuery::message->answer(...)`) work without a global lookup.

Revolt's `FiberLocal` is per-fiber storage; a per-update concurrent
dispatch (`PollingOptions::$handleAsTasks > 0`) gets its own bound
slot in its own fiber, so cross-bot contamination is structurally
impossible.

### Sharing a session across multiple bots

Sessions are designed to be reusable. Multiple bots can share the same
session — for example, a multi-tenant deployment hands one
`AmphpSession` to every `Bot` so they pool the underlying
`HttpClient`'s connection pool:

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

// Share one session — pools the HTTP connection across both tokens.
$session = new AmphpSession();
$bot1 = new Bot(getenv('BOT_TOKEN') ?: '111:aaa', $session);
$bot2 = new Bot(getenv('BOT_TOKEN_2') ?: '222:bbb', $session);

$dispatcher = new Dispatcher();

$dispatcher->message->register(static function (Message $event, Bot $bot): void {
    $tokenId = explode(':', $bot->token)[0];
    $event->answer("Bot #{$tokenId} received: " . ($event->text ?? ''))->emit();
});

// runPolling accepts variadic bots; each gets its own polling fiber.
$dispatcher->runPolling(new PollingOptions(), $bot1, $bot2);
```

Bots are not coupled to their session beyond the constructor handshake;
calling `$bot->session` returns the attached instance and tests can
swap it via reflection if necessary.

### Shortcut generation

The shortcut layer is generated alongside the facade. Codegen reads
the Bot API schema's response types and emits `Message::answer`,
`CallbackQuery::answer`, `ChatJoinRequest::approve`, etc. The shortcut
constructs the appropriate method DTO bound to the calling event's
chat/message identifiers and returns it, so handlers write
`$event->answer($text)->emit()` instead of building a `SendMessage`
DTO explicitly. The `emit()` call routes back through the bot bound
by the dispatcher's FiberLocal — the same path a manual
`$bot(new SendMessage(...))` would take.

## Trade-offs

The serializer is reflection-based, not codegen-output. Each request
walks the DTO's public properties to build the snake_case wire payload;
each response walks the typed `ReturnsType` to hydrate the result. This
costs one `ReflectionClass` per shape per request but trades the
runtime cost for codegen simplicity — Phase 2's generator produces the
Methods/Types tree without also having to emit per-method
serializers. The serializer reflects on every `dump`/`load` rather
than caching type metadata; only the camelCase→snake_case name
conversion is memoised (`Serializer::$camelToSnakeCache`).

`BaseSession::__invoke` always runs the request through the middleware
manager, even when the chain is empty. That single closure compose is
the price of letting users insert telemetry, retry, or rate-limit
middleware without changing the call site. For the empty chain the
middleware manager short-circuits to the bare `makeRequest` closure, so
the overhead is one extra function call per request — negligible
against the network round-trip but worth knowing if you wrap the bot
in a tight loop.

There is no built-in retry-on-network-error. `RestartingTelegram` and
`TelegramNetworkException` propagate to the caller; the dispatcher's
polling loop has its own backoff. Custom retry policies belong in a
request middleware so the bot itself stays predictable. We chose not
to ship a default retry middleware because policy varies wildly across
deployments — exponential backoff with jitter is right for some bots,
fail-fast with alerting is right for others.

The bot is *not* thread-safe in the traditional sense — PHP has no
threads — but it is fiber-safe through the session's `__invoke`
contract. Two fibers calling the same bot concurrently each get their
own `makeRequest` closure invocation; the underlying HTTP client
handles connection-pool concurrency. The `Bot::setCurrent` slot is
per-fiber via `FiberLocal`, so concurrent dispatch on multiple bots
each see their own binding even when the dispatcher fans out to a
semaphore-bounded pool.

## See also

- [Dispatcher](dispatcher.md)
- [Serialization](serialization.md)
- [Error model](error-model.md)
- [API reference: Bot](https://api.phpbotgram.local/Gruven-PhpBotGram-Bot.html)
- [API reference: BaseSession](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-BaseSession.html)
