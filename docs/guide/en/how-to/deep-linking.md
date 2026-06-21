# Handle deep-link `/start` payloads

## When to use this

Send users a `https://t.me/yourbot?start=ref_abc` link and pick up the payload server-side. Useful for referral codes, magic-login tokens, and product deep-links. The framework builds the URL and the `CommandStart` filter extracts the payload on receipt.

## Solution

```php
use Gruven\PhpBotGram\Filters\CommandStart;
use Gruven\PhpBotGram\Filters\CommandObject;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\DeepLinking;

// Building the link.
$link = DeepLinking::createStartLink($bot, payload: 'ref_abc', encode: false);
// → https://t.me/yourbot?start=ref_abc

// Handling the incoming /start with payload.
$dispatcher->message->register(
    static function (Message $event, CommandObject $command): void {
        $payload = $command->args ?? '';
        $event->answer("Welcome! Your ref code: {$payload}")->emit();
    },
    filters: [new CommandStart(deepLink: true)],
);
```

[`DeepLinking::createStartLink`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-DeepLinking.html) caches the bot's username via a `WeakMap` so subsequent calls don't re-hit `getMe`. Pass `encode: true` (or a custom encoder) to base64 arbitrary payloads — raw payloads are limited to `[A-Za-z0-9_-]` and 64 characters. On receipt, the `CommandObject` injected by `CommandStart` carries the raw payload in `$command->args`. If you used `encode: true`, decode it explicitly with [`Payload::decode`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Payload.html).

The full runnable version is [`examples/deep_linking.php`](https://github.com/Gruven/phpbotgram/blob/master/examples/deep_linking.php).

## Pitfalls

- Telegram caps the raw payload at 64 ASCII characters. Anything outside `[A-Za-z0-9_-]` throws `InvalidArgumentException`. Use `encode: true` for arbitrary strings.
- `createStartLink` requires the bot to have a username — bots without one (rare) throw `LogicException` from the first call.
- The payload arrives **after** the `/start` token. Use [`CommandStart`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-CommandStart.html) not plain `Command` so the framework normalises the parse.
- See [Filters](../concepts/filters.md) for `CommandObject` semantics.
