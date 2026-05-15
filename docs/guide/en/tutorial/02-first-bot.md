# Your first bot

Build a polling echo bot in 20 lines. Replies to every message with the
same text.

## Get a token

Talk to [@BotFather](https://t.me/BotFather) on Telegram, create a new
bot, and copy the token (looks like `123456:ABCdefGHIjklMNOpqrSTUvwxYZ`).

## Write the bot

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

$token = getenv('BOT_TOKEN') ?: throw new RuntimeException('BOT_TOKEN missing');
$bot = new Bot($token);
$dispatcher = new Dispatcher();

$dispatcher->message->register(static function (Message $event): void {
    $text = $event->text ?? '';
    if ($text === '') return;
    $event->answer($text)->emit();
});

$dispatcher->runPolling(new PollingOptions(), $bot);
```

## Run it

```bash
BOT_TOKEN=123456:ABCdef… php echo_bot.php
```

Send any text to your bot in Telegram; it echoes back.

See the [full example](examples/echo_bot.php) for the version with
graceful-shutdown handling.

## What just happened

- `new Bot($token)` constructs a [`Bot`](https://api.phpbotgram.local/Gruven-PhpBotGram-Bot.html)
  with the default `AmphpSession` HTTP transport.
- `Dispatcher::runPolling()` loops `getUpdates` against Telegram, feeding
  every update through the registered handlers.
- `$event->answer(...)` is a codegen-produced shortcut that builds a
  `SendMessage` already bound to the right chat.

## Next step

[Add filters and dispatch on commands →](03-handlers-and-filters.md)
