# Error model

Errors in phpbotgram split into two channels: typed Telegram API
failures (the server said no) and dispatcher-synthetic error events
(a handler raised). The framework typed both so you can catch
selectively.

## How it works

### Telegram API exceptions

API failures are
[`TelegramApiException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramApiException.html)
subclasses. The session inspects the HTTP status and the response
body's `error_code` / `description` fields, then throws the most
specific subclass:
[`TelegramBadRequestException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramBadRequestException.html)
for 400s,
[`TelegramRetryAfter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramRetryAfter.html)
for 429 with the `retry_after` payload,
[`TelegramConflictException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramConflictException.html)
for 409 (another `getUpdates` already running),
[`TelegramForbiddenException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramForbiddenException.html)
for 403,
[`TelegramNotFoundException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramNotFoundException.html)
for 404, and the server-side `5xx` branch through
[`TelegramServerException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramServerException.html).
[`TelegramNetworkException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramNetworkException.html)
wraps transport-level failures (connection refused, TLS handshake
errors). The hierarchy mirrors aiogram one-for-one so catch-by-type
ports without surprise.

Catching a specific subclass in a handler is straightforward:

```php
use Gruven\PhpBotGram\Exceptions\TelegramForbiddenException;
use Gruven\PhpBotGram\Types\Message;

$handler = static function (Message $event): void {
    try {
        $event->answer('Hello!')->emit();
    } catch (TelegramForbiddenException $e) {
        // bot was blocked by the user; log and move on
    }
};
```

### Handler errors and the errors observer

Handler exceptions take a different path.
[`ErrorsMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Middlewares-ErrorsMiddleware.html),
wired into the dispatcher automatically, catches any throw inside a
handler and constructs an
[`ErrorEvent`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-ErrorEvent.html)
holding the original `Update` plus the `Throwable`. It then re-enters
`propagateEvent('error', ...)` so a registered error observer can claim
the failure.

Unlike Telegram update events, `ErrorEvent` does **not** extend
`TelegramObject` — it lives in `Types/` only because aiogram puts it
there, but it's a standalone readonly value object with no serializer /
bot plumbing. Error handlers register on `$dispatcher->errors` (or
`$router->errors`) and follow the same filter/middleware contract as any
other observer.

[`ExceptionTypeFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-ExceptionTypeFilter.html)
matches by `instanceof`;
[`ExceptionMessageFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-ExceptionMessageFilter.html)
matches by regex against `getMessage()`. Together they let an error
observer chain handlers selectively — the example below fires only on
`TelegramRetryAfter`, not on every error:

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Filters\ExceptionTypeFilter;
use Gruven\PhpBotGram\Types\ErrorEvent;

$bot = new Bot(getenv('BOT_TOKEN'));
$dispatcher = new Dispatcher();

$dispatcher->errors->register(
    static function (ErrorEvent $event): void {
        // Narrow for static analysis; the filter already guarantees the type.
        $exception = $event->exception;
        if ($exception instanceof TelegramRetryAfter) {
            fwrite(STDERR, "Flood wait {$exception->retryAfter}s on this bot.\n");
        }
    },
    filters: [new ExceptionTypeFilter(TelegramRetryAfter::class)],
);

$dispatcher->runPolling(new PollingOptions(), $bot);
```

The framework also ships a
[`TokenValidationException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TokenValidationException.html)
for malformed tokens at bot construction time, distinct from the
runtime API exceptions.

### Control-flow exceptions

Two control-flow exceptions are internal signalling primitives, not
errors.
[`SkipHandlerException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Event-SkipHandlerException.html)
tells the dispatcher to skip the current handler and try the next one;
[`CancelHandlerException`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Event-CancelHandlerException.html)
tells it to abandon the dispatch entirely. Throwing either from a
handler is the supported way to fall through or abort —
`ErrorsMiddleware` recognises these by type and re-raises them
without converting to an `ErrorEvent`. The naming follows aiogram's
`SkipHandler` / `CancelHandler` exceptions verbatim.

```php
use Gruven\PhpBotGram\Dispatcher\Event\CancelHandlerException;
use Gruven\PhpBotGram\Dispatcher\Event\SkipHandlerException;
use Gruven\PhpBotGram\Types\Message;

// Skip this handler; the dispatcher tries the next registered handler.
$skipHandler = static function (Message $event): void {
    if (($event->text ?? '') === '') {
        throw new SkipHandlerException();
    }
    $event->answer('Got text!')->emit();
};

// Abandon the entire dispatch for this update; no further handlers run.
$cancelHandler = static function (Message $event): void {
    if (($event->fromUser?->isBot ?? false)) {
        throw new CancelHandlerException();
    }
    $event->answer('Hello, human!')->emit();
};
```

### Polling-loop failures

Polling-loop failures have their own contract. A
[`RestartingTelegram`](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-RestartingTelegram.html)
or `TelegramNetworkException` in `getUpdates` routes through
[`Backoff`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Backoff.html)
— exponential delay with jitter, configured per dispatcher via
`PollingOptions::$backoffConfig` (note the field is `$backoffConfig`,
not `$backoff`).

`TelegramRetryAfter` is special-cased: the loop sleeps for the *exact*
`retryAfter` seconds the API advertised, then retries without consulting
the backoff. The distinction matters: backoff is for unknown network
trouble, `retry_after` is an explicit flood-wait contract and growing
the delay beyond the advertised value would just waste throughput.

## Trade-offs

The typed hierarchy is wide. A general "log everything" error
handler reads `Throwable` and looks at `getMessage()`; a fine-grained
handler that does retry-on-network catches `TelegramNetworkException`
specifically. The wide hierarchy is a cost (more classes to know
about) for a benefit (catch-by-type instead of inspecting an
error-code string). Aiogram makes the same trade. The hierarchy
matches Telegram's documented error-code grouping, so a handler that
catches `TelegramApiException` and tests `getErrorCode()` is doing
extra work the typed catch already does.

`ErrorEvent` deliberately does not extend `TelegramObject`. This
saves the dispatcher from running the serializer on an error event
(which would be meaningless — it has no wire form) and clarifies
the type's role. The cost is that error handlers cannot use
`TelegramObject`-typed methods on the value; the benefit is that
the framework cannot accidentally try to send an error event back
to Telegram.

`SkipHandler` and `CancelHandler` are exceptions, not return-values.
PHP's typed return values would have made this awkward — `mixed` is
the contract, and a sentinel `RejectedSentinel` already exists for
"this handler didn't match". Using exceptions for the *intended*
control flow is unusual but readable: `throw new SkipHandler;` is
clearer than `return RejectedSentinel::instance();` at the call site.
The cost of exception-based control flow is one stack-unwind per
throw, which is negligible against the rest of dispatch.

There is no built-in retry middleware. We could ship one ("retry
TelegramRetryAfter N times before giving up") but policy varies
enough that a default would be wrong for most deployments. The
right place for a custom retry is a session middleware (it sees the
outbound call) or a dispatcher middleware (it sees the inbound
event); both seams are stable.

The `silentCallRequest` mechanism on the dispatcher swallows
`TelegramApiException` from the late webhook-fallthrough path and
emits a `RuntimeWarning`. The reasoning is that the request lifecycle
is already over by the time the late call surfaces, and there's
nowhere to surface the failure — except as a warning to logs. Bots
that need to alert on this specific case should attach a logging
handler that watches for `E_USER_WARNING` from the framework.

## See also

- [Dispatcher](dispatcher.md)
- [Middlewares](middlewares.md)
- [API reference: TelegramApiException](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramApiException.html)
- [API reference: ErrorEvent](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-ErrorEvent.html)
- [API reference: TelegramRetryAfter](https://api.phpbotgram.local/Gruven-PhpBotGram-Exceptions-TelegramRetryAfter.html)
