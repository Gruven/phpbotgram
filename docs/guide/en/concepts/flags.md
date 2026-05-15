# Flags

A flag is a per-handler metadata tag — a name plus an optional value —
that middlewares and filters read at dispatch time to decide whether to
apply per-handler behaviour (auth, throttling, chat actions, …).

## How it works

[`Flag`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Flags-Flag.html)
is a small readonly value object plus a PHP attribute. As an attribute
it can be repeated on a method, function, or class, so a single handler
can carry `#[Flag('admin_only')]`, `#[Flag('chat_action', 'typing')]`,
and `#[Flag('throttle', 0.5)]` simultaneously. As a value object the
same `Flag` instance ends up in the handler's `$flags` list at
registration time. The dual role lets the same primitive serve both the
declarative (`#[Flag(...)]` on the method) and imperative
(`$obs->register($cb, flags: [...])`) styles. The attribute's
`#[Attribute(...IS_REPEATABLE...)]` tagging is what makes "stack any
number of flags" work; aiogram uses a `dict[str, Any]` because Python
has no equivalent attribute mechanism.

Attribute-driven flags are read via PHP reflection. Imperative flags
are stored in a process-wide
[`FlagDecorator`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Flags-FlagDecorator.html)
`WeakMap` keyed by the target closure or object. The weak-map trick
exists because PHP cannot mutate arbitrary properties on a `Closure` —
Python's `cb.aiogram_flag = {...}` translates to a side-table here. The
weak-map evicts entries automatically when the closure is
garbage-collected, so the storage is bounded by live handlers — there
is no manual cleanup or registry maintenance needed even when the
dispatcher rebuilds its handler graph (e.g. when a scene re-registers
its method handlers).

[`Flags`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Flags-Flags.html)
is the read API. `Flags::extractFlags($target)` returns both attachment
styles concatenated (imperative first, then attribute-driven).
`Flags::getFlag($target, 'admin_only')` returns the first match or
`null`. Middlewares that read flags use these helpers — they never
touch the WeakMap directly. The `extractFlagsFromObject` helper on
the `HandlerObject` itself bakes the read into the dispatch hot path
so a per-event lookup costs one `getFlag` call against a typically-small
list.

The framework ships two flag-aware utilities.
`CallbackAnswerMiddleware` (in `Utils/CallbackAnswer/`) reads
`#[Flag('callback_answer')]` and auto-acknowledges the callback query
before the handler runs, with the parameters from the flag's value
shaping the acknowledgement text. `ChatActionMiddleware` (in
`Utils/ChatAction/`) reads `#[Flag('chat_action', 'typing')]` and
sends a `chat_action` API call that loops in the background until the
handler returns — visible to the user as the "typing..." indicator
during long-running operations. Both follow the same pattern: read the
flag, optionally do prep work, delegate to the handler, then run
cleanup.

[`FlagGenerator`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Flags-FlagGenerator.html)
is the helper that converts a flag list into a normalised form for
inspection. The dispatcher does not use it on the hot path — it's
exposed for tooling and tests that want to assert "this handler has
exactly these flags". Production middlewares should use the
`Flags::getFlag` shortcut instead.

## Trade-offs

Flags are unstructured by design. A flag's `value` is `mixed` — any
type, any shape — because middlewares own the interpretation. This
is flexible but means a typo in a flag name (`'admin_onyl'`) fails
silently. There is no central registry of legal flag names; the
contract is `(middleware, flag-name)` pairs documented at the
middleware's call site. We considered an enum-typed flag-name surface
and rejected it: middlewares ship in user code as often as in the
framework, and forcing every user middleware to extend a framework
enum would not scale.

The WeakMap trick depends on the target being an object. String
callables or `[$obj, 'method']` arrays cannot carry flags this way —
the registration adapter lifts them via `Closure::fromCallable(...)`
first. For attribute-driven flags this matters only when the
attribute is on the method itself; the attribute path is independent
of WeakMap storage. The lift to a closure is automatic and idempotent;
users do not normally see it. PHP's closure equality is identity-based,
so the WeakMap correctly distinguishes two closures over the same
function.

Flag values are read at dispatch time, every dispatch. There is no
cache. A flag-aware middleware running on a hot observer pays a
reflection-and-WeakMap lookup per event. The cost is small (one
`getFlag` against a typically-empty list) but real — if you find
yourself adding flags to every handler, consider moving the policy
into the middleware itself, where it can short-circuit faster. The
profile-driven choice is to keep the lookup hot-path simple and let
heavy middlewares opt out by checking a per-instance toggle rather
than caching the flag read.

Two attachment styles also mean two read paths. `Flags::extractFlags`
concatenates them in a deterministic order (imperative first), so
imperative flags override attribute-driven ones on `getFlag` lookups.
This matters when a middleware-level imperative flag should *win*
against a class-level attribute default — a common pattern for
per-route overrides.

## See also

- [Middlewares](middlewares.md)
- [API reference: Flag](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Flags-Flag.html)
- [API reference: Flags](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Flags-Flags.html)
- [API reference: FlagDecorator](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Flags-FlagDecorator.html)
- [API reference: FlagGenerator](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Flags-FlagGenerator.html)
