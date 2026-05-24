# Keyboards

The keyboard builders are fluent grid managers for inline and reply
keyboards. They guard against Telegram's per-row and per-keyboard
button limits and produce the typed `InlineKeyboardMarkup` /
`ReplyKeyboardMarkup` DTOs the API expects.

## How it works

### Builder base and subclasses

[`KeyboardBuilder`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Keyboard-KeyboardBuilder.html)
is the abstract base. It owns a two-dimensional `list<list<T>>` button
grid and exposes `add(T ...$buttons)` (append to the current row),
`row(list<T> $buttons)` (start a new row), and `adjust(int ...$sizes)`
(re-flow the linear button stream into rows of the given widths). The
generic `T` is bound at the subclass level:
[`InlineKeyboardBuilder`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Keyboard-InlineKeyboardBuilder.html)
binds to
[`InlineKeyboardButton`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-InlineKeyboardButton.html),
[`ReplyKeyboardBuilder`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Keyboard-ReplyKeyboardBuilder.html)
binds to
[`KeyboardButton`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-KeyboardButton.html).
The `@template T of object` PHPDoc tag keeps PHPStan honest across
the abstract API.

### Limits

Limits are enforced at build time. `MAX_WIDTH`, `MIN_WIDTH`, and
`MAX_BUTTONS` are class constants on each subclass —
`InlineKeyboardBuilder::MAX_WIDTH` is 8 (Telegram's documented per-row
cap) and `MAX_BUTTONS` is 100. The builders throw
`InvalidArgumentException` on overflow so a layout bug fails at
registration time, not in the wild. `adjust()` validates against
`MIN_WIDTH`/`MAX_WIDTH` before re-flowing so a programmatic mistake
(`adjust(0)`) surfaces immediately rather than producing a malformed
markup that Telegram rejects later. The reply-keyboard limits are
different from inline — reply keyboards permit more buttons per row
(10) and more total buttons (300); the per-subclass constants
encode each variant's actual limit.

### Inline keyboard

Build an inline keyboard with `InlineKeyboardBuilder`, then pass
`asMarkup()` to `$event->answer(..., replyMarkup: ...)`:

```php
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\Keyboard\InlineKeyboardBuilder;

$dispatcher->message->register(static function (Message $event): void {
    $kb = new InlineKeyboardBuilder();
    $kb->button(text: 'Option A', callbackData: 'choice:a');
    $kb->button(text: 'Option B', callbackData: 'choice:b');
    $kb->adjust(2);

    $event->answer('Pick one:', replyMarkup: $kb->asMarkup())->emit();
});
```

`button()` is the convenience factory: it constructs an
`InlineKeyboardButton` from named parameters and calls `add()`.
`adjust(2)` re-flows the buttons into rows of width 2. `asMarkup()`
wraps the grid in an
[`InlineKeyboardMarkup`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-InlineKeyboardMarkup.html)
DTO ready for the API.

### Reply keyboard

`ReplyKeyboardBuilder` works the same way; `asMarkup()` accepts
display options (`resizeKeyboard`, `oneTimeKeyboard`, …) and returns a
[`ReplyKeyboardMarkup`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-ReplyKeyboardMarkup.html):

```php
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\Keyboard\ReplyKeyboardBuilder;

$dispatcher->message->register(static function (Message $event): void {
    $kb = new ReplyKeyboardBuilder();
    $kb->button(text: 'Yes');
    $kb->button(text: 'No');
    $kb->button(text: 'Share contact', requestContact: true);
    $kb->adjust(2);   // Yes / No on the first row, Share contact on the second

    $markup = $kb->asMarkup(resizeKeyboard: true, oneTimeKeyboard: true);
    $event->answer('Choose:', replyMarkup: $markup)->emit();
});
```

Reply-specific button capabilities (`requestContact`, `requestLocation`,
`requestPoll`, `requestUsers`, `requestChat`) are typed parameters on
the `button()` factory, so the construction site reads
`$kb->button(text: 'Share', requestContact: true)` — no separate
builder method needed. The same typed-parameter pattern applies to
`InlineKeyboardButton` fields (`url`, `callbackData`, `webApp`,
`loginUrl`, …).

### Terminal call and inspection

`asMarkup()` is the terminal call. The builders are throwaway — call
`asMarkup()` once when you're done and the resulting markup is
immutable. The markup's button-grid property (`inlineKeyboard` for
inline keyboards, `keyboard` for reply) is a fresh deep copy of the
builder's grid, so further mutation on the builder doesn't leak into
the produced markup.

`buttons()` returns a flat `Generator<int, T>` over every cell in the
grid; `export()` returns a deep-copy of the grid as `list<list<T>>`.
The generator is useful for cross-cutting checks (does any callback
button overflow 64 bytes?); the export is useful for tests that
want a stable assertion target. Both expose the grid without
preserving the builder reference, so they're safe to hand to a
consumer that might mutate.

## Trade-offs

The builders are mutable. Each `add`/`row`/`adjust` call mutates
`$this->markup` in place — chaining is by return-this convention. This
is unusual for a project that leans heavily on `readonly`, but
keyboards are a one-shot ephemeral build artefact, not a long-lived
value. Once `asMarkup()` runs, the builder is discarded. The
mutable-builder + immutable-markup split mirrors the StringBuilder
pattern from other languages — a fluent build phase followed by a
crystalline output.

There is one `KeyboardBuilder` per builder-target. We didn't try to
share a single builder with a runtime mode flag — the type-parameter
ergonomics matter (an inline builder should not accept a reply
button), and the duplicated `asMarkup()` body is small. Both
subclasses share the grid-management logic in the abstract base.
A single shared builder would also force the user to remember which
mode it was in, and a typed pair is just clearer.

There is no auto-pagination. A keyboard that overflows `MAX_BUTTONS`
throws; there is no built-in "spill onto a `next page` button". This
is deliberate — pagination semantics are application-specific (do you
want page numbers, prev/next, page-of-N?) and a generic implementation
would be either too rigid or so configurable it adds complexity for
the simple case. Users compose their own pagination keyboards from
the builder primitives. Several examples in the test suite and
`examples/` directory show common patterns.

`adjust()` is destructive. It flattens the existing grid into a
linear button list, then re-flows into the requested row widths.
Callers who want to preserve a previous layout should `export()`
first, mutate a copy, and feed it into a fresh builder via the
constructor's `$markup` parameter. The constructor's pre-existing
markup is deep-cloned on assign, so external mutation doesn't leak in.

## See also

- [CallbackData](callback-data.md)
- [Text decoration](text-decoration.md)
- [API reference: KeyboardBuilder](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Keyboard-KeyboardBuilder.html)
- [API reference: InlineKeyboardBuilder](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Keyboard-InlineKeyboardBuilder.html)
- [API reference: ReplyKeyboardBuilder](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Keyboard-ReplyKeyboardBuilder.html)
