# Architecture decisions

This page records the deliberate divergences from aiogram. The port
stays faithful where it can; the divergences below are where idiomatic
PHP demanded a different shape.

## How it works

phpbotgram tracks aiogram 3.x as the reference design. The package
structure mirrors `aiogram/dispatcher`, `aiogram/filters`, `aiogram/fsm`,
and so on; every class has a one-to-one upstream counterpart that the
docblock cites by file and line.

Where the port departs, the reason is either a PHP-language constraint,
an idiomatic-PHP rewrite, or a bug fix the port took the opportunity to
apply. These departures are documented inline in the class docblocks. The
[`Dispatcher`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html)
docblock, for instance, lays out its three responsibilities and a
"Spec deviations from upstream" list, each with its reasoning.

### No async/await — sync-style public surface

The largest structural divergence is **no async/await**. Python
`asyncio` coroutines suspend on `await` and yield control to the event
loop. PHP has no `await` keyword; the closest analogue is amphp v3
fibers, which suspend by calling `Future::await()` *from inside a
fiber*.

The port adopts a sync-style public surface: every public method
returns a concrete value, never a `Future`. Implementations may suspend
internally — a session HTTP call, a Redis storage lookup — but the
suspension is invisible to handlers. The cost is one extra event-loop
tick per call site that *would* have been awaited; the benefit is
handler signatures that read like ordinary PHP and don't infect callers
with `Future` boilerplate. The choice is sticky: every subsystem in the
framework follows the sync-style contract, so a single async-leaking
method would be visibly out of place.

### Explicit scene registration

The second-largest is **explicit scenes** instead of metaclass-driven
auto-discovery. Aiogram's `Scene` subclass registration uses Python's
`__init_subclass__` hook to wire scenes into the global registry the
moment they are defined. PHP has no equivalent; we considered `Composer`
autoloader hooks but rejected them as too implicit.

The port requires explicit `SceneRegistry::add([MyScene::class, ...])`
calls. A class that looks like a scene but isn't registered does nothing
— the failure mode is "you forgot to register me" rather than "the
framework silently never knew about you". See the
[Scenes](scenes.md) concept page for the consequences.

### Reflection-based serialization

Third: **no `model_dump`-style codegen serializers**. Aiogram leans on
Pydantic, which generates a fast-path validator/serializer per class at
definition time. The port uses reflection-based serialization through
[`Serializer`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Serializer.html).

The trade is performance vs. codegen complexity — Phase 2's generator
already produces 700+ classes; doubling the surface to include per-class
serializers would have multiplied the template count and the regen time.
The reflection path is fast enough for production loads — it reflects
per call rather than caching type metadata; see the
[Serialization](serialization.md) page.

### Minor divergences

- The dispatcher attaches the global middleware chain
  (`UserContextMiddleware`, `ErrorsMiddleware`) at the *ingress* (in
  `feedUpdate`) rather than on a synthetic `update` observer. Wrapping
  at ingress avoids the double-wrap regression a per-observer approach
  would cause on multi-router trees.
- `silentCallRequest` is a public *instance* method, not the
  `@classmethod` aiogram uses. PHP test scaffolding cannot patch a
  class method cleanly; subclasses such as `RecordingDispatcher`
  override the instance method to intercept calls.
- `Filter::all` and `Filter::any` exist as static helpers because
  PHP cannot overload `&` and `|`. The semantics match aiogram's
  operator forms one-for-one. See
  [`Filter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Filter.html).
- `Bot::setCurrent` is the FiberLocal equivalent of Python's
  `contextvars` binding inside a `with bot.context():`. Revolt's
  `FiberLocal` is the closest analogue; the dispatcher binds and
  clears the slot in a try/finally so a handler exception cannot
  leak the binding into the next dispatch.
- `Command::of(...)` is a variadic factory that
  [`Command`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Command.html)
  exposes because PHP forbids parameters after a variadic. Aiogram's
  `Command('start', 'help', ignore_case=True)` shape collapses to
  `Command::of('start', 'help')` plus a named-argument flag, with the
  underlying constructor accepting `array|string $commands` for the
  array form.
- `Filter` is variadic-by-design: every concrete filter takes
  `(object $event, mixed ...$kwargs)` so `CallableObject::prepareKwargs`
  passes through the entire dispatcher kwargs bag. A non-variadic
  filter would silently lose context kwargs at dispatch time.

### Upstream bug fixes

The port also takes the opportunity to fix several upstream bugs that
surfaced as we re-implemented.

The `feedWebhookUpdate` 55-second deadline runs the dispatch in the
background after the deadline expires, so a late `TelegramMethod` still
reaches Telegram via `silentCallRequest`. The `pollingFor` loop swallows
`UpdateTypeLookupException` with a `RuntimeWarning` so a Bot API schema
regression (new update kind) doesn't kill the polling loop. Both are
documented at the source with the upstream issue reference.

## Trade-offs

These divergences are not just renamings. Each represents a place where
the port could have closer-to-upstream parity at the cost of unidiomatic
PHP, and chose idiomatic PHP instead. Code that pastes aiogram patterns
verbatim will *work* — the API surface tracks upstream — but will look
slightly off-kilter against PHP norms. The README's quickstart and the
tutorial pages emphasise the PHP-idiomatic form. We did not chase
verbatim parity for its own sake; readability matters more.

The flip side: developers coming from aiogram will recognise the
shape of every subsystem. The dispatcher walks updates, filters vote,
middlewares wrap, FSM stores state. We did not redesign anything for
its own sake; every divergence has a one-paragraph reason. The
`docs/superpowers/specs/` tree records the design conversations in
more depth than this page can.

There are intentional non-divergences too. The 25-slot Update observer
map, the `outer`/`inner` middleware split, the F-DSL operator-tree
form, the scene-history rollback model — all kept as in upstream. Those
patterns *do* translate cleanly to PHP, and changing them would have
fragmented the cross-language audience for no gain. The non-divergences
are by far the larger set; the divergences listed here are the
exceptions, not the rule.

## See also

- [Dispatcher](dispatcher.md)
- [FSM](fsm.md)
- [Scenes](scenes.md)
- [API reference: Dispatcher](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html)
- [API reference: Filter](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Filter.html)
