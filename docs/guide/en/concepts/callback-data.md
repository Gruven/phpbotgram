# CallbackData

`CallbackData` is the typed payload encoding for inline-keyboard callback queries. Subclasses declare a prefix and a constructor; `pack()` and `unpack()` round-trip between PHP objects and the 64-byte wire string Telegram allows in `CallbackQuery::$data`.

## How it works

### Defining a payload type

[`CallbackData`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-CallbackData.html) is an abstract base. A subclass tags itself with [`#[CallbackPrefix('order')]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-CallbackPrefix.html) and exposes constructor-promoted readonly properties. `pack()` walks the constructor's parameter list (not the property set) to build the wire string `prefix:val1:val2:...`. Iterating parameters guarantees field order matches across encode and decode, and excludes derived properties assigned in the constructor body — matching Pydantic's `model_dump()` semantic of "fields, not attributes".

```php
use Gruven\PhpBotGram\Filters\CallbackData;
use Gruven\PhpBotGram\Filters\CallbackPrefix;

#[CallbackPrefix('order')]
final class OrderCallback extends CallbackData
{
    public function __construct(
        public readonly int    $id,
        public readonly string $action,
        public readonly bool   $deleted = false,
    ) {}
}
```

The type-encoding table is fixed: `null → ''`, `bool → '1'/'0'`, `int|float → (string)$value`, `string → as-is`, `\Stringable → (string)$value`, `\UnitEnum → $value->value`, anything else throws `LogicException`. Decoding does the inverse: a typed property constructor parameter drives the reverse coercion (`?int` parses back from the integer string, nullable types parse `''` back to `null`, backed enums route through `::from`). The fixed table avoids the "what does this type encode to" question on a per-call basis — the choices are pinned to upstream `aiogram/filters/callback_data.py` verbatim.

`MAX_CALLBACK_LENGTH = 64` enforces Telegram's wire-length cap. `pack()` measures with `strlen` — PHP strings are byte sequences and the protocol expects UTF-8, so byte-count equals UTF-8-byte-count when the input is valid UTF-8. Overflow throws `LogicException`, not `InvalidArgumentException`: a too-long callback payload is a programming error (you chose the field shape), not user input. The limit is hard-coded on Telegram's side; the framework cannot soften it without breaking the wire contract.

### Packing a payload into a button

[`InlineKeyboardBuilder::button()`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Keyboard-InlineKeyboardBuilder.html) accepts a `CallbackData` instance directly and calls `pack()` for you:

```php
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\Keyboard\InlineKeyboardBuilder;

// Pack a payload into a button.
$keyboard = (new InlineKeyboardBuilder())
    ->button('Confirm',  callbackData: new OrderCallback(id: 42, action: 'confirm'))
    ->button('Cancel',   callbackData: new OrderCallback(id: 42, action: 'cancel'))
    ->asMarkup();

// Attach to a reply.
// $event->answer('Pick an action:', replyMarkup: $keyboard)->emit();
```

### Registering a handler and unpacking the payload

The matching filter is `OrderCallback::filter()` — a static factory that returns a [`CallbackQueryFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-CallbackQueryFilter.html) configured to match the subclass's prefix. Handlers receive the unpacked object via the `callback_data` kwarg:

```php
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Types\CallbackQuery;

$dispatcher = new Dispatcher();

// Register a handler that fires only for OrderCallback payloads.
$dispatcher->callbackQuery->register(
    static function (CallbackQuery $event, OrderCallback $callback_data): void {
        $event->answer("Order #{$callback_data->id} — {$callback_data->action}")->emit();
    },
    filters: [OrderCallback::filter()],
);
```

`$callback_data` is already unpacked — no manual `CallbackData::unpack()` call needed. The filter rejects any `CallbackQuery` whose `data` string does not begin with the `order:` prefix, so the handler only runs for well-shaped payloads.

The separator defaults to `:` but can be overridden via the attribute: `#[CallbackPrefix('order', sep: '|')]`. Choose the separator carefully — it cannot appear in any field's encoded value. For most bots the default colon is safe; bots that encode UUIDs or other string values that might contain colons should pick a separator unlikely to collide.

## Trade-offs

64 bytes is small. After the prefix and separators, you have ~50 bytes for actual data — a few small ints, no UUIDs, no free-form text. The encoding deliberately favours compactness over human-readability: a `bool` is `'1'`, not `'true'`, because the wire cap is the binding constraint. If your payload doesn't fit, store it server-side and put a short ID in the callback data. The pattern is "callback ID is a key into a server-side store, not a self-contained payload" — and aiogram makes the same recommendation.

Reflection runs once per `pack`/`unpack` call, against the subclass metadata. The cost is real but small (≈ tens of microseconds on a modern PHP); for hot paths the caller can cache the meta object themselves. We deliberately do not cache it inside `CallbackData` — the per-subclass meta is reflected on demand to avoid leaking a static map that would interfere with hot-reload during development. The reflection is bounded by the number of distinct `CallbackData` subclasses in your bot, not by request volume.

Subclasses with non-promoted properties are tolerated but excluded from the wire form. This mirrors Pydantic but trips up authors who expect "all properties serialize". The constructor's parameter list is the single source of truth, full stop. The class docblock states this explicitly so the surprise is documented at the right level. If you genuinely need a computed property to round-trip, compute it on the consumer side from the wire fields.

The framework supports backed enums in callback fields, but the enum must be `BackedEnum`-typed (not just `UnitEnum`). Encoding uses `$value->value` — a non-backed enum has no value to encode. The decoder uses `::from`, so an invalid enum string surfaces as the underlying `\ValueError`. We treat this as user-data error (the wire payload was corrupted or out of date) rather than a programming error, so the typed exception propagates to the dispatcher's error channel.

## See also

- [Filters](filters.md)
- [Keyboards](keyboards.md)
- [API reference: CallbackData](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-CallbackData.html)
- [API reference: CallbackPrefix](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-CallbackPrefix.html)
- [API reference: CallbackQueryFilter](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-CallbackQueryFilter.html)
