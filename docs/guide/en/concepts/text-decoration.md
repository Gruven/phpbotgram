# Text decoration

`TextDecoration` turns plain text plus a list of `MessageEntity`
entries into a Telegram-flavoured Markdown V2 or HTML string. It is
the inverse of Telegram's "parse_mode" — given the structured form,
produce the wire string.

## How it works

### Strategy and subclasses

[`TextDecoration`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Text-TextDecoration.html)
is the abstract strategy. Concrete subclasses
[`HtmlDecoration`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Text-HtmlDecoration.html)
and
[`MarkdownDecoration`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Text-MarkdownDecoration.html)
implement the per-entity emitters (`bold`, `italic`, `code`,
`preLanguage`, `link`, `customEmoji`, …) plus the escape-for-quoting
function (`quote`). The recursive emitter `applyEntity` dispatches on
`MessageEntityType` via a `match (true)` expression — bold goes to
`bold()`, code goes to `code()`, and so on. Pass-through types
(`Url`, `Mention`, `Hashtag`, `Email`, …) return the inner text
unchanged because escaping would corrupt them.

Both subclasses expose a singleton via `instance()` so you never need
to construct them manually:

```php
use Gruven\PhpBotGram\Enums\MessageEntityType;
use Gruven\PhpBotGram\Enums\ParseMode;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Utils\Text\HtmlDecoration;

$dispatcher->message->register(static function (Message $event): void {
    $text = 'Hello world visit us';
    $entities = [
        new MessageEntity(type: MessageEntityType::Bold->value,   offset: 0, length: 5),
        new MessageEntity(type: MessageEntityType::Italic->value,  offset: 6, length: 5),
        new MessageEntity(
            type: MessageEntityType::TextLink->value,
            offset: 18,
            length: 2,
            url: 'https://example.com',
        ),
    ];

    $wire = HtmlDecoration::instance()->unparse($text, $entities);
    // $wire === '<b>Hello</b> <i>world</i> visit <a href="https://example.com">us</a>'

    $event->answer($wire, parseMode: ParseMode::Html->value)->emit();
});
```

Pass the result to `$event->answer(..., parseMode: ParseMode::Html->value)` or
`ParseMode::MarkdownV2->value` to tell Telegram how to interpret the wire string.

### unparse and UTF-16 offsets

`unparse(string $text, ?list<MessageEntity> $entities)` is the top-level
entry. It sorts the entities by offset, then walks them. Telegram's
entity offsets are in **UTF-16 code units**, not bytes or PHP characters
— a surrogate-pair emoji is two units. The implementation converts the
input to UTF-16LE via `mb_convert_encoding`, indexes by 2-byte units,
and converts back to UTF-8 at each yield. This is the only correct way
to handle the offset semantics; substring-by-byte would corrupt any
text past the first non-ASCII code point. The PHP port matches
aiogram's same UTF-16 handling at the implementation level.

The walk handles nested entities. An entity fully inside another
(`<b>hello <i>world</i></b>`) is collected as a nested batch and
recursively decorated before the outer entity wraps the result. This
matches Telegram's documented "entities can nest" rule and matches
upstream `text_decorations.py`'s implementation. Overlapping (but
not nested) entities are not supported — the API rejects them anyway
on the inbound side, so an outbound rendering pipeline never has
to handle them. The recursive walk is a `Generator` that yields
decorated segments in order; `unparse` joins them once at the top
level.

### quote — escaping plain text

The `quote` method on each subclass is the escape function for the
target dialect. `MarkdownDecoration::quote` escapes the Markdown V2
special-character set defined by the Telegram Bot API
(underscore, asterisk, tilde, backslash, square brackets, parentheses,
backtick, greater-than, hash, plus, minus, equals, pipe, curly braces,
period, exclamation mark). `HtmlDecoration::quote` escapes the three
XML-sensitive characters (less-than, greater-than, ampersand). Apply
it to any text segment that is not already inside an entity emitter —
`unparse` does this automatically for untagged gaps between entities,
so user code rarely calls `quote` directly.

When you need to embed user-supplied text safely in a Markdown V2
message without constructing `MessageEntity` objects, call `quote`
yourself before concatenating:

```php
use Gruven\PhpBotGram\Enums\ParseMode;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\Text\MarkdownDecoration;

$dispatcher->message->register(static function (Message $event): void {
    $md = MarkdownDecoration::instance();

    // quote() escapes special Markdown V2 characters in plain text.
    $username = $event->fromUser?->username ?? 'stranger';
    $safe = $md->quote("Hello, @{$username}! (your score: 100%)");

    $event->answer($safe, parseMode: ParseMode::MarkdownV2->value)->emit();
});
```

### Entity dispatch and extensibility

The `MessageEntityType` enum drives the dispatch. Each Telegram
entity type maps to a method on the decoration subclass. Adding a
new entity type (which Telegram does occasionally) is a Phase 2
codegen update plus two method overrides on each decoration
subclass — small, mechanical work.

The public surface is `unparse()` plus `quote()`; the per-entity
emitters (`bold`, `italic`, …) stay `protected`. A handler that wants
to compose a Markdown V2 message manually can escape user input with
[`MarkdownDecoration::quote`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Text-MarkdownDecoration.html)
and concatenate it with its own markup. The framework's preferred path
is to assemble the structured form (text + entities) and let `unparse`
produce the wire string.

## Trade-offs

UTF-16 conversion runs on every `unparse` call. For long messages the
cost is non-trivial — proportional to the text length, not the entity
count. We do not cache because messages are typically built once and
sent once; if you find yourself rendering the same `Message`
repeatedly (unusual), cache the result yourself. The conversion is
necessary *because* of Telegram's wire-protocol contract, not a
choice. The two `mb_convert_encoding` calls per unparse are the
unavoidable cost of supporting non-BMP characters correctly.

Pass-through types are an asymmetry. A URL entity returns its inner
text unchanged, including any reserved-character collisions with the
target dialect. The HTML decoration escapes `<` and `>` everywhere,
even inside a `Url` entity — but Markdown V2 does not, because the
URL syntax already handles escaping internally. The trade is "match
Telegram's accepted formatting" vs. "make every output universally
safe"; the framework chooses the former, matching aiogram. A URL
that genuinely contains an angle bracket has to be encoded
upstream of the message build.

Date-time entities require a `unixTime` plus optional format string.
The decoration emits a Telegram `tg://datetime?date=...` link for
HTML and the equivalent Markdown V2 form. This is a Telegram-internal
extension; the standard parse modes don't include it but the
framework supports it because the upstream entity type does. The
`dateTimeFormat` argument follows PHP's `DateTimeImmutable::format`
syntax, since the value is a Unix timestamp at the wire level and
gets formatted client-side.

Custom emoji entities embed the emoji ID. The decoration emits a
`tg://emoji?id=...` URL for both HTML and Markdown V2 forms. Telegram
clients render this as the actual custom emoji, but clients that
don't support custom emoji (or for media that doesn't support them
at all) see the underlying ID — there's no automatic fallback to a
generic emoji.

## See also

- [Keyboards](keyboards.md)
- [Bot and Session](bot-and-session.md)
- [API reference: TextDecoration](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Text-TextDecoration.html)
- [API reference: HtmlDecoration](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Text-HtmlDecoration.html)
- [API reference: MarkdownDecoration](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Text-MarkdownDecoration.html)
