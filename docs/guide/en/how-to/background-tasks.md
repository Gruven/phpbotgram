# Run code outside the dispatcher

## When to use this

Periodic jobs (refresh a cache, scrape an RSS feed, post a daily
digest) and one-shot background work (send a delayed reminder) need
to live outside the dispatcher fiber so they don't block update
processing. `amphp` exposes `async()` and `delay()` — combine them
for fire-and-forget tasks.

## Solution

```php
use function Amp\async;
use function Amp\delay;

use Gruven\PhpBotGram\Types\Message;

$dispatcher->message->register(static function (Message $event, Bot $bot): void {
    $chatId = $event->chat->id;

    // Send the immediate reply.
    $event->answer('I will remind you in 30 seconds.')->emit();

    // Fire-and-forget reminder.
    async(static function () use ($bot, $chatId): void {
        delay(30.0);
        $bot->sendMessage(chatId: $chatId, text: 'Reminder!');
    });
});
```

`async()` queues the closure on the Revolt event loop; control
returns to the handler immediately. `delay()` suspends the
background fiber without blocking other fibers. The same pattern
powers
[`ChatActionSender::raceDelay()`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-ChatAction-ChatActionSender.html)
— a private helper that races a `delay` against a cancellation
future so the loop can stop cleanly.

## Pitfalls

- `async()` swallows exceptions silently unless you call `->await()`
  on the returned `Future`. Wrap the body in `try/catch` or chain
  `->ignore()` to suppress the unhandled-future warning explicitly.
- Background work runs in the same process. A `runPolling()` exit
  (Ctrl-C) tears down the loop and pending fibers; persist anything
  that must survive.
- Don't call blocking PHP (`sleep`, `curl_exec`, blocking I/O) inside
  a fiber — it stalls every other fiber. Use `amphp/http-client` or
  wrap blocking libs in `async()` just to schedule them off the main
  loop.
- See [Dispatcher](../concepts/dispatcher.md) for the fiber lifecycle.
