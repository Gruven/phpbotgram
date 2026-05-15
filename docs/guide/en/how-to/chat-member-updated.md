# Track chat-member transitions

## When to use this

Welcome new joiners, archive leavers, audit promotions — anything
that fires when someone's chat membership status changes. The
framework exposes `my_chat_member` and `chat_member` updates with a
filter that matches by old/new status pairs.

## Solution

```php
use Gruven\PhpBotGram\Filters\ChatMemberUpdatedFilter;
use Gruven\PhpBotGram\Types\ChatMemberUpdated;

// Pre-built: anyone joining the chat.
$dispatcher->chatMember->register(
    static function (ChatMemberUpdated $event): void {
        $name = $event->newChatMember->user->firstName ?? 'a user';
        $event->bot->sendMessage(
            chatId: $event->chat->id,
            text: "Welcome, {$name}!",
        );
    },
    filters: [ChatMemberUpdatedFilter::join()],
);

// Custom transition: any member becoming an administrator.
$dispatcher->chatMember->register(
    static function (ChatMemberUpdated $event): void {
        // Audit log…
    },
    filters: [ChatMemberUpdatedFilter::transition(
        from: ChatMemberUpdatedFilter::MEMBER,
        to: ChatMemberUpdatedFilter::IS_ADMIN,
    )],
);
```

[`ChatMemberUpdatedFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-ChatMemberUpdatedFilter.html)
ports aiogram's `JOIN_TRANSITION`/`LEAVE_TRANSITION` constants as the
factory methods `join()`, `leave()`, `promotion()`, `demotion()`.
`transition(from:, to:)` accepts the public status lists
(`MEMBER`, `IS_ADMIN`, `IS_MEMBER`, `IS_NOT_MEMBER`, etc.).

## Pitfalls

- Subscribe to `chat_member` updates explicitly via
  [`PollingOptions::$allowedUpdates`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-PollingOptions.html)
  — Telegram strips them by default. `my_chat_member` arrives
  unconditionally.
- The `restricted` status is collapsed into `IS_MEMBER` regardless of
  `is_member`. For finer control, build a `transition()` manually and
  post-filter on `oldChatMember->isMember`.
- The bot must be an admin in the chat to receive `chat_member` events
  for OTHER users — Telegram restriction, not framework. See
  [Filters](../concepts/filters.md) for the predicate model.
