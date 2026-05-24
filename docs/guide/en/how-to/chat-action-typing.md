# Show "typing…" while a slow handler runs

## When to use this

A handler that calls a slow external API leaves the user staring at a silent chat. Telegram's `sendChatAction` shows a `typing…` indicator for ~5 seconds — refresh it in a background loop and the user sees activity until your reply lands.

## Solution

```php
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\ChatAction\ChatActionSender;

$dispatcher->message->register(static function (Message $event, Bot $bot): void {
    $reply = ChatActionSender::typing(
        bot: $bot,
        chatId: $event->chat->id,
    )->scope(function () {
        // Slow work — API call, image render, OCR.
        return slowWork();
    });

    $event->answer($reply)->emit();
});
```

[`ChatActionSender`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-ChatAction-ChatActionSender.html) spins a fiber that re-sends the action every `interval` seconds (5 by default) until `scope()`'s closure returns or throws.

Eleven factory methods cover every Telegram action — `typing`, `uploadPhoto`, `recordVoice`, `uploadDocument`, `chooseSticker`, etc. The handle returned by `start()` lets you control the loop manually if `scope` is too restrictive.

## Pitfalls

- `scope()` swallows the closure's value and returns it through to the caller — exceptions still propagate. Don't wrap-then-rethrow.
- `sendChatAction` failures inside the loop are silenced — a flaky network shouldn't kill background indicators. Real errors come from your work, not the indicator.
- Forgetting to call `stop()` on a manual `start()` leaks the fiber. Always use `scope()` unless you have a state machine reason not to. See [Dispatcher](../concepts/dispatcher.md) for the fiber lifecycle.
