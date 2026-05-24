# Handle errors globally

## When to use this

Bots that run unattended need to log uncaught exceptions instead of losing them to the polling loop. Register an error observer once on the dispatcher and every handler — across every router — inherits it.

## Solution

```php
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Types\ErrorEvent;

$dispatcher = new Dispatcher();
$dispatcher->errors->register(static function (ErrorEvent $event): void {
    error_log(sprintf(
        '[%s] uncaught: %s — %s',
        date('c'),
        get_class($event->exception),
        $event->exception->getMessage(),
    ));
});
```

The `errors` observer fires whenever a handler raises an uncaught exception. The [`ErrorEvent`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-ErrorEvent.html) carries both the original update and the raised throwable. Returning without re-raising swallows the exception; rethrowing escalates to the polling loop's exit path.

## Pitfalls

- The error observer runs in the same fiber as the failing handler. If it raises again, the dispatcher logs and continues — but the update is lost. Keep error handlers free of network I/O.
- Errors raised inside `outerMiddleware` *before* dispatch reach `Dispatcher::errors` only if the middleware re-enters the observer loop. See [Middlewares](../concepts/middlewares.md) for the call order.
- `TelegramRetryAfter` is *not* delivered to `errors`; the polling loop's backoff (`PollingOptions::$backoffConfig`) handles it directly.
