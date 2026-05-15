# FSM

The Finite State Machine subsystem keeps per-user state across
multiple updates — so a multi-step form can ask "what's your name?"
in one update and remember the answer when the next update arrives.

## How it works

[`FsmContext`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmContext.html)
is the per-request handle a handler receives. It carries a
[`StorageKey`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-StorageKey.html)
(the address: bot + chat + user + optional thread)
and a [`BaseStorage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-BaseStorage.html)
reference, and exposes `setState`, `getState`, `setData`, `getData`,
`updateData`, `clearData`, `clearState` — sync-style methods that
delegate to the storage. Storage backends may suspend internally on
Redis or Mongo I/O, but the public surface returns concrete values,
never `Future`s. The handle is short-lived: it's constructed by
`FsmContextMiddleware` from the resolved address and the dispatcher's
storage reference at the start of each dispatch, and discarded when
the handler returns.

The address shape is driven by
[`FsmStrategy`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmStrategy.html).
Five cases — `UserInChat` (the default), `Chat`, `GlobalUser`,
`UserInTopic`, `ChatTopic` — each map `(chatId, userId, threadId)`
into the canonical `StorageKey` triple. Choose `UserInChat` for
per-conversation state, `GlobalUser` for cross-chat user
preferences, `ChatTopic` for forum-topic-scoped state, and so on.
Strategy lives on the dispatcher (constructor argument), so it
applies uniformly to every handler — there is no per-handler
override. Mixing strategies in one bot is unsupported by design;
if you genuinely need it, run two dispatchers.

Storage backends are pluggable. Three ship in-tree:
[`MemoryStorage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-MemoryStorage.html)
(the default; lost on process restart),
[`RedisStorage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-RedisStorage.html),
and
[`MongoStorage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-MongoStorage.html).
Each implements `BaseStorage` and serialises state as a string and
data as a JSON-encodable dict. The MemoryStorage backend is fine
for development and testing but not for production — a process
restart wipes every user's state mid-conversation. The Redis and
Mongo backends each ship with their own
[`KeyBuilder`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-KeyBuilder.html)
adapter so the `StorageKey` triple becomes a backend-appropriate
identifier (a Redis key prefix vs. a Mongo document `_id`).

State predicates use the [`StateFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-StateFilter.html).
A handler registered with
`filters: [new StateFilter(MyStates::WaitingForName)]` runs only
when the user's current state matches.
[`State`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-State.html)
and [`StatesGroup`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-StatesGroup.html)
classes give you compile-checked state references — a typo in a
state name surfaces as a class-resolution error, not silent
mismatch.
[`FsmContextMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmContextMiddleware.html)
wires automatically when the dispatcher is constructed without
`disableFsm: true`, so every handler can declare `function (Message
$event, FsmContext $state)` to receive a ready-to-use context.

Event isolation prevents the FSM from racing against itself when
two updates for the same key arrive concurrently.
[`BaseEventIsolation`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-BaseEventIsolation.html)
is the policy seam; the default
[`SimpleEventIsolation`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-SimpleEventIsolation.html)
serialises dispatches per address with an in-memory lock,
[`RedisEventIsolation`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-RedisEventIsolation.html)
does the same across processes via Redis SET-NX, and
[`DisabledEventIsolation`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-DisabledEventIsolation.html)
skips the lock entirely for bots that genuinely tolerate races. The
isolation owner lives on the dispatcher; you don't reach for it
directly unless you're tuning concurrency.

## Trade-offs

The MemoryStorage default is a foot-gun. It makes the quickstart
work without configuring Redis, but a developer who ships
to production without swapping storage will find every user's
FSM state evaporate on every deploy. The README and tutorials
flag this; the framework itself does not refuse to boot, because
testing legitimately uses memory storage. We considered a "you
should explicitly choose storage" startup check and rejected it as
too noisy.

State is a single string, data is a JSON-serialisable dict. There
is no per-field schema, no migration system. If you add a field to
your state payload and a user has stale data from before, your
handler must tolerate the absence. This is intentional — a typed
state machine on top of an FSM is a different abstraction (try
Scenes), and bolting one onto the storage layer would couple the
storage to a specific application's lifecycle. Aiogram makes the
same choice.

Storage operations are not transactional across keys. `setState`
and `setData` are separate calls; an unlucky crash between them
leaves a state without its data, or vice versa. For most flows
this is acceptable (the handler re-asks the question and recovers);
for strict transactional needs, store the lot under one key with
`setData` only. The framework does not try to expose a transactional
primitive because backends differ — Redis MULTI vs. Mongo
transactions vs. in-memory locks don't compose into one cross-backend
API without leaking implementation details.

The event isolation lock is held for the duration of the dispatch.
A slow handler blocks subsequent events for the same key until it
returns. This is usually what you want (it prevents lost updates) but
means a handler that calls an external API for 30 seconds backs up
the user's other events for 30 seconds. Set `DisabledEventIsolation`
when you've validated your handlers don't need the protection.

## See also

- [Scenes](scenes.md)
- [API reference: FsmContext](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmContext.html)
- [API reference: FsmStrategy](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmStrategy.html)
- [API reference: BaseStorage](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-BaseStorage.html)
- [API reference: StateFilter](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-StateFilter.html)
