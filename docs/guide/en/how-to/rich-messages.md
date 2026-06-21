# Send rich messages

## When to use this

Use `sendRichMessage` when a response needs Bot API 10.1 rich formatting instead of a plain `sendMessage` text/entity pair. Rich messages are useful for generated reports, multi-section answers, and assistant-style output where Telegram should preserve headings, lists, code blocks, media blocks, and right-to-left layout metadata as a structured message.

## Solution

### Send a complete rich message

```php
use Gruven\PhpBotGram\Types\InputRichMessage;

$html = <<<'HTML'
<h1>Status update</h1>
<p><b>Build:</b> green</p>
<ul>
  <li>Tests passed</li>
  <li>Docs published</li>
</ul>
HTML;

$bot->sendRichMessage(
    chatId: $chatId,
    richMessage: new InputRichMessage(
        html: $html,
        skipEntityDetection: true,
    ),
);
```

[`InputRichMessage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-InputRichMessage.html) accepts either `html` or `markdown`. The bot facade wraps it in a generated [`SendRichMessage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Methods-SendRichMessage.html) DTO, so `disableNotification`, `protectContent`, `replyMarkup`, and the usual message-scoped options work the same way they do on `sendMessage`.

### Reply from a handler

```php
use Gruven\PhpBotGram\Types\InputRichMessage;
use Gruven\PhpBotGram\Types\Message;

$dispatcher->message->register(static function (Message $event): void {
    $event->replyRich(new InputRichMessage(
        markdown: "# Thanks\n\nYour request is queued.",
    ))->emit();
});
```

`Message::answerRich()` sends to the same chat. `Message::replyRich()` also fills `replyParameters` from the source message, mirroring the text-message `answer()` / `reply()` shortcut split.

### Stream a temporary draft

```php
use Gruven\PhpBotGram\Types\InputRichMessage;

$bot->sendRichMessageDraft(
    chatId: $chatId,
    draftId: $draftId,
    richMessage: new InputRichMessage(
        markdown: 'Generating the final answer...',
    ),
);
```

[`sendRichMessageDraft`](https://api.phpbotgram.local/Gruven-PhpBotGram-Methods-SendRichMessageDraft.html) is for temporary 30-second generation previews. Telegram treats the draft as ephemeral; call `sendRichMessage` with the final `InputRichMessage` when the output is ready.

### Read received rich content

```php
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\RichBlockParagraph;

$dispatcher->message->register(static function (Message $event): void {
    $rich = $event->richMessage;

    if ($rich === null) {
        return;
    }

    foreach ($rich->blocks as $block) {
        if ($block instanceof RichBlockParagraph) {
            // Inspect $block->text, which can be a string, RichText, or a list of segments.
        }
    }
});
```

Incoming rich content hydrates as a [`RichMessage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-RichMessage.html) with typed [`RichBlock`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-RichBlock.html) and [`RichText`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-RichText.html) descendants. The serializer keeps the Bot API's recursive text shape: a text field may be a plain string, a single rich-text DTO, or a list mixing strings and nested rich-text segments.

## Pitfalls

- Set exactly one of `InputRichMessage::$html` or `InputRichMessage::$markdown`. Telegram rejects payloads that omit both or try to send both.
- Rich messages are not `sendMessage` plus `parseMode`; use `sendRichMessage`, `replyRich`, or the `richMessage:` named argument on `editMessageText`.
- Media blocks still require the bot to have permission to send that media type in the target chat.
- Drafts are 30-second previews, not persisted messages. Send the final rich message explicitly.
- When constructing rich text manually, prefer generated DTOs over raw arrays. Raw arrays are supported for hydrated wire payloads, but DTOs keep application code typed.
