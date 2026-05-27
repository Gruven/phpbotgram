# Run multiple bots from one process

## When to use this

Running two related bots (production + staging, EN bot + RU bot, support + sales) in the same process keeps memory low and lets them share handler code. `runPolling` is variadic — pass every `Bot` instance and the dispatcher fans out one fiber per bot.

## Solution

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

$bot1 = new Bot(getenv('BOT_TOKEN'));
$bot2 = new Bot(getenv('BOT_TOKEN_2'));

$dispatcher = new Dispatcher();

// The injected $bot kwarg is the specific bot that received the update.
$dispatcher->message->register(static function (Message $event, Bot $bot): void {
    $id = explode(':', $bot->token)[0];
    $event->answer("Bot #{$id} received: {$event->text}")->emit();
});

$dispatcher->runPolling(new PollingOptions(), $bot1, $bot2);
```

[`Dispatcher::runPolling`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html) spawns one polling fiber per bot and merges every incoming update through the same handler tree. The `Bot $bot` kwarg in any handler identifies which bot the current update came from — handy for per-tenant logic.

## Pitfalls

- Handlers share state. If you keep an in-memory counter, both bots increment it. Namespace per `$bot->token` (or per `$event->chat->id`) when isolation matters.
- The `MemoryStorage` is single-key-spaced — both bots' FSM contexts collide unless you key by `bot_id`. Promote to Redis/Mongo and rely on the `StorageKey` `botId` field for natural separation.
- `stopPolling()` halts every fiber, not a single bot. Run separate dispatchers per bot if you need independent lifecycles. See [Bot and Session](../concepts/bot-and-session.md) for the one-session-per-bot guarantee.
