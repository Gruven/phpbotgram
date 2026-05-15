# F-DSL

`F` is a top-level constant that seeds a `MagicFilter` chain. The chain
records attribute access, method calls, and comparisons, then resolves
against a Telegram event when the dispatcher asks.

## How it works

[`MagicFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MagicFilter-MagicFilter.html)
is a lazy expression tree. Each `__get($name)` and `__call($name,
$args)` returns a *new* `MagicFilter` instance with one extra operation
appended to its internal `$operations` list; the original chain is
immutable. The `F` constant at the top of `Gruven\PhpBotGram\F` is a
fresh, empty `MagicFilter` — PHP 8.5 allows `new` in `const`
expressions, so the import `use const Gruven\PhpBotGram\F;` gives every
caller the same seed without runtime allocation. Composer's
`autoload.files` entry forces the constant file to load eagerly since
PSR-4 only handles class symbols.

A chain like `F->message->text->equals('hello')` builds three
operations:
[`GetAttributeOperation('message')`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MagicFilter-Operation-GetAttributeOperation.html),
`GetAttributeOperation('text')`,
[`ComparatorOperation::equals('hello')`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MagicFilter-Operation-ComparatorOperation.html).
To turn it into a `Filter` the caller invokes `->asFilter()`, which
wraps the chain in
[`MagicFilterAsFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MagicFilter-MagicFilterAsFilter.html).
That bridge resolves the chain against the *event* — so the example
above succeeds when `$event->message->text === 'hello'`. Operations
implement a uniform `BaseOperation::resolve(mixed $value): mixed`
contract; the resolver walks the list applying each operation in
turn.

PHP cannot overload `&` / `|` / `~`, so logical composition uses named
methods. `$f->and_($g)`, `$f->or_($g)`, and `$f->not()` build
`CombinationOperation` and `NotOperation` nodes. Comparators
(`equals`, `gt`, `lt`, `in_`, `contains`, `regexp`, `func`, `cast`)
each append a typed operation; the runtime resolver knows how to apply
each one against the chain's current value. Type coercion follows
upstream `magic_filter`: a `MagicFilter::WILDCARD_ALL` sentinel means
"every element", `WILDCARD_ANY` means "any element", and
`F->items[MagicFilter::WILDCARD_ALL]` matches Python's `F[:]` empty-slice
case.

Resolution distinguishes important operations from rest. When an
attribute lookup or cast fails, the chain enters a "skip" state — the
running value collapses to `null` and only `important` operations
continue (NOT, OR, …). This is how `F->message->text->equals('hi')`
gracefully returns `false` for an `EditedMessage` event with no `text`
slot, instead of throwing. The "important" mechanism is what makes
`OR` short-circuiting work cleanly: the left branch can reject without
poisoning the right branch.
[`MagicData`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-MagicData.html)
is the variant that resolves against the *kwargs* bag rather than the
event — useful when a rule depends on FSM state or another
middleware-injected value. The bag is wrapped in
[`AttrDict`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MagicFilter-AttrDict.html)
so the chain's `__get` semantics work transparently over an
associative array.

The F-DSL is what aiogram users mean by "magic filter". The PHP
port matches the expression-tree shape almost verbatim, plus aiogram's
local `as_()` extension which lets a chain extract a value into the
kwargs bag rather than just voting accept/reject. The `func()` operation
takes a `callable(mixed): bool` so user-supplied predicates can join
the chain.

## Trade-offs

The DSL trades static type-checking for ergonomics. PHPStan cannot see
through `F->message->text` — it sees a `MagicFilter`, period. The
chain becomes type-erased at the point of construction. We mitigate by
producing readable runtime errors (the resolver reports the failing
operation by index), but if you want fully-typed predicates, a hand-
written `Filter` subclass is the right tool. Aiogram has the same
limitation; mypy/pyright don't see through `F.message.text` either.

Each chain operation allocates a fresh `MagicFilter` and copies the
operations array. For typical chains (3–5 operations) the cost is
trivial; for chains built in a loop, prefer composing once outside the
loop and resolving inside. The chain is immutable, so caching the
built `Filter` across dispatches is safe — in fact, the dispatcher
caches the filter list per handler so the chain is built only on
registration, not on dispatch.

`asFilter()` is the only bridge to the dispatch contract. A bare
`MagicFilter` cannot be registered — it has no `__invoke(object,
...$kwargs): array|bool` signature. The asymmetry is deliberate:
chains are values, filters are dispatch-time predicates. The
distinction lets the resolver run repeatedly against different events
without the chain itself having to know about the dispatch protocol.

The "skip on missing attribute" behaviour is *almost* invisible but
matters when authoring custom operations. A user-supplied
`func()` predicate sees `null` when an upstream attribute lookup
failed; it should not throw on null. The resolver's important-op
mechanism handles NOT and OR; everything else has to tolerate `null`
defensively or be marked important.

Comparators support `Stringable`. `F->text->equals($someUuid)` works
because the comparator coerces both sides via `(string)`. This is
useful for value-object IDs but can mask "I meant to compare ints,
not strings" bugs. The `equalsStrict` variant exists for cases where
type coercion is unwanted.

## See also

- [Filters](filters.md)
- [CallbackData](callback-data.md)
- [API reference: MagicFilter](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MagicFilter-MagicFilter.html)
- [API reference: MagicFilterAsFilter](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MagicFilter-MagicFilterAsFilter.html)
- [API reference: MagicData](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-MagicData.html)
