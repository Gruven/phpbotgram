# Rate-limit outgoing API calls

## When to use this

Telegram caps outgoing calls at ~30/second globally and ~20/minute per chat. Bursting beyond the limit earns a `429 Too Many Requests` with a `retry_after` payload. The framework's polling loop and the `TelegramRetryAfter` exception cooperate to back off cleanly.

## Solution

### Configure the polling backoff

```php
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Utils\BackoffConfig;

// Tune the long-poll retry budget (polling fibers).
$options = new PollingOptions(
    backoffConfig: new BackoffConfig(
        minDelay: 1.0,
        maxDelay: 30.0,
        factor: 2.0,
        jitter: 0.5,
    ),
);
```

### Retry inside a handler

```php
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\Backoff;
use Gruven\PhpBotGram\Utils\BackoffConfig;

// Hand-rolled backoff inside a handler when Telegram answers 429.
$backoff = new Backoff(new BackoffConfig());

$dispatcher->message->register(static function (Message $event) use ($backoff): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $event->answer('Hi')->emit();
            $backoff->reset();
            return;
        } catch (TelegramRetryAfter $e) {
            $backoff->asleep();
        }
    }
});
```

[`Backoff`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Backoff.html) stages exponential delays from a [`BackoffConfig`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-BackoffConfig.html); `asleep()` suspends the current fiber for `currentDelay` seconds, then advances.

The polling loop uses the same primitive for `getUpdates` retries via [`PollingOptions::$backoffConfig`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-PollingOptions.html).

## Pitfalls

- The `TelegramRetryAfter` exception carries the server-suggested delay; prefer `$e->retryAfter` over `$backoff` for short stalls and fall back to backoff for sustained pressure.
- Jitter is uniform `[-jitter, +jitter]`, not normal. Concurrent bots therefore avoid retry-lockstep without ever exceeding `maxDelay` (the cap is applied AFTER jitter).
- `Backoff` is mutable per-instance; share one per concurrent call site and `reset()` after success. See [Error model](../concepts/error-model.md) for the exception hierarchy.
