# Serialization

The serializer turns Telegram type DTOs into snake_case-keyed wire
payloads and back. It is the boundary between the strongly-typed PHP
domain model and the dynamic-typed Bot API.

## How it works

[`Serializer`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Serializer.html)
is reflection-driven. `Serializer::dump($object)` iterates the public
properties of any
[`BotContextController`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-BotContextController.html)
subclass (every `TelegramObject` and `TelegramMethod`), converts each
camelCase property name to snake_case, and emits a flat
`array<string, mixed>` ready to JSON-encode or form-urlencode. Nested
`BotContextController` instances recurse; lists and maps walk
element-by-element. An `Unspecified::instance()` value signals "this
field was never set" and is skipped — distinct from `null`, which is
preserved so the wire can carry an explicit null.

`Serializer::load($class, $rawArray, $bot)` is the inverse. It
reflects the target class's constructor, walks the parameter list,
converts each parameter's PHP name to snake_case to look up the
wire key, and coerces the value via the parameter's declared type.
Nested `TelegramObject` parameters route through `Serializer::load`
recursively. The `$bot` argument is threaded into every nested
constructor's optional `?Bot $bot` parameter so handler shortcuts
(`$message->answer(...)`) work on hydrated trees. The recursion
walks union types correctly via PHP's `ReflectionUnionType` — a
parameter typed `Message|null` decodes the wire payload's expected
shape and accepts the `null` case if the wire delivers a `null`.

Per-class wire-name overrides live in a class-level `WireNames`
constant. Phase 2 codegen produces them when a Telegram field
name doesn't camelCase cleanly to a valid PHP identifier — the
upstream `from` reserved-word collision becomes `$fromUser` in PHP
with `const WireNames = ['fromUser' => 'from']` driving the
serializer mapping. The override mechanism keeps the default
`camelToSnake` cheap and pushes special cases to the per-class
metadata where they belong. The serializer reads the constant via
reflection at class-load time; subsequent dumps/loads hit a cached
copy.

[`BaseSession`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-BaseSession.html)
owns two adjacent hooks. `prepareValue` runs after `Serializer::dump`
to detach `InputFile` instances from the form body (uploads go in
multipart fields, references stay in the JSON) and to strip null
values from the form encoding (form bodies don't represent `null`
cleanly; the wire shape uses absence). `checkResponse` runs on the
decoded response: it validates the `ok: true` field, throws the
matching `TelegramApiException` subclass on `ok: false`, and routes
the success payload through `Serializer::load` for the typed return.
The two hooks bracket the network round-trip: dump → prepareValue
→ HTTP → checkResponse → load.

DateTime fields use a dedicated wrapper. The framework's
[`Types\Custom\DateTime`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-Custom-DateTime.html)
type carries a `DateTimeImmutable` plus its original Unix-timestamp
representation; the serializer routes `int → DateTime` on load and
`DateTime → int` on dump. This preserves precision (`DateTimeImmutable`
keeps seconds + microseconds) while staying faithful to Telegram's
wire format (Unix timestamp as integer). Handlers can use the
`DateTimeImmutable` API without re-parsing the timestamp on every
read.

The `BotDefault` mechanism lets a `Bot` carry default values that
populate `Unspecified` fields on outbound methods. The serializer
checks for default presence after `Unspecified` skip but before the
wire emit — so a `SendMessage` left with
`parseMode: Unspecified::instance()` picks up the bot's default
parse mode if one was configured. The hook is in
[`BotDefault`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-BotDefault.html);
defaults are scoped per bot so multi-tenant deployments can configure
each bot independently.

## Trade-offs

Reflection-based serialization costs one `ReflectionClass` per shape
per request. The serializer caches a `reflectMeta` table internally
so repeated dumps of the same class skip the work, but the cache is
not exposed and grows with the number of distinct types seen. For a
typical bot that touches 20–30 type shapes the cache is bounded;
synthetic-test scenarios that build hundreds of one-off subclasses
can grow it unboundedly. We considered exposing the cache for
manual eviction and decided the complexity wasn't worth it — bots
that hit the issue can null out `static` state directly via tests.

Codegen-output serializers would be faster. Aiogram uses Pydantic,
which builds a fast-path validator at class definition time. The PHP
port deliberately chose reflection: emitting per-class serializers
during Phase 2 would have doubled the codegen surface and required a
second template engine. The reflection cost is real but small, and
hot paths can always cache the meta themselves. The 10x or so speedup
codegen serializers could deliver is dwarfed by the network round-trip
on every Bot API call, so the trade pays itself in code clarity.

`Unspecified::instance()` is a sentinel, not `null`. The distinction
matters because Telegram fields are tri-state: present-with-value,
present-and-null (explicit nullability), absent. A naive
`if ($value !== null)` would conflate the last two; the
`Unspecified` sentinel preserves the difference. The cost is one
extra concept to learn — handlers that build method DTOs almost
always want to leave a field at `Unspecified::instance()` to mean
"don't send". The default value for every method parameter is
`Unspecified::instance()`, so the surprise is contained to "what is
this sentinel doing here".

The `InputFile` detachment in `prepareValue` is form-only. JSON
encoding (the default for Bot API methods that don't carry files)
serialises `InputFile` references inline; the detachment kicks in
only when the session falls back to multipart form-data because of
an attached file. The branching lives in the session, not the
serializer — keeping the serializer ignorant of transport details.

Wire-name overrides are a per-class constant rather than property
attributes. We considered `#[WireName('from')]` on each property and
chose the constant for codegen simplicity: emitting one constant per
class is cheaper than emitting one attribute per property. The cost
is the override table is class-level, not property-level — a class
with multiple wire-name overrides has them all in one constant, which
is fine for the small number of overrides Bot API actually requires.

## See also

- [Bot and Session](bot-and-session.md)
- [Error model](error-model.md)
- [API reference: Serializer](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Serializer.html)
- [API reference: BaseSession](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-BaseSession.html)
- [API reference: BotContextController](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-BotContextController.html)
