# FSM

The Finite State Machine subsystem keeps per-user state across
multiple updates — so a multi-step form can ask "what's your name?"
in one update and remember the answer when the next update arrives.

## How it works

### Declaring states

State names are declared as `public static State` properties on a
[`StatesGroup`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-StatesGroup.html)
subclass. The class is passive at definition time; call `bootstrap()`
(or rely on `bootstrapIfNeeded()`, which `StateFilter` calls automatically)
to let the framework discover the properties via reflection and wire
their qualified names.

```php
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\StatesGroup;

class Form extends StatesGroup
{
    public static State $name;
    public static State $age;
}
Form::bootstrap();
```

After bootstrap, `Form::$name->state()` resolves to `'Form:name'` and
`Form::$age->state()` to `'Form:age'`. A typo in a property name
surfaces as a class-resolution or reflection error — not a silent
string mismatch at runtime.

Nested groups are declared via the `CHILDREN` class constant:
`public const array CHILDREN = [SubGroup::class]`. The parent group's
`bootstrap()` call recurses into all children automatically.

### Reading and writing state via FsmContext

[`FsmContext`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmContext.html)
is the per-request handle a handler receives. It carries a
[`StorageKey`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-StorageKey.html)
(the address: bot + chat + user + optional thread)
and a [`BaseStorage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-BaseStorage.html)
reference, and exposes `setState`, `getState`, `setData`, `getData`,
`updateData`, `getValue`, `clear` — sync-style methods that
delegate to the storage. Storage backends may suspend internally on
Redis or Mongo I/O, but the public surface returns concrete values,
never `Future`s. The handle is short-lived: it's constructed by
`FsmContextMiddleware` from the resolved address and the dispatcher's
storage reference at the start of each dispatch, and discarded when
the handler returns.

A typical multi-step form reads and writes state like this (adapted
from `examples/finite_state_machine.php`):

```php
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\StateFilter;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\StatesGroup;
use Gruven\PhpBotGram\Types\Message;

class Form extends StatesGroup
{
    public static State $name;
    public static State $age;
}
Form::bootstrap();

// /start — enter the form by setting the initial state.
$dispatcher->message->register(
    static function (Message $event, FsmContext $state): void {
        $state->setState(Form::$name);
        $event->answer("What's your name?")->emit();
    },
    filters: [new Command('start')],
);

// Collect name — only fires when state === Form:name.
$dispatcher->message->register(
    static function (Message $event, FsmContext $state): void {
        $state->updateData(['name' => $event->text ?? '']);
        $state->setState(Form::$age);
        $event->answer("How old are you?")->emit();
    },
    filters: [new StateFilter(Form::$name)],
);
```

`updateData` merges new keys into the existing payload without wiping
previously stored keys. Use `setData` when you want to replace the
payload entirely. Pass `null` to `setState` to clear the state and
end the flow.

### Gating handlers with StateFilter

[`StateFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-StateFilter.html)
accepts a `State` instance, a raw string, or the wildcard `'*'`.
[`FsmContextMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmContextMiddleware.html)
wires automatically when the dispatcher is constructed without
`disableFsm: true`, so every handler can declare `function (Message
$event, FsmContext $state)` to receive a ready-to-use context.

### Choosing a storage backend and strategy

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

Swap to Redis by passing `storage` and optionally `fsmStrategy` to
the `Dispatcher` constructor:

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Fsm\FsmStrategy;
use Gruven\PhpBotGram\Fsm\Storage\RedisStorage;

$bot = new Bot(getenv('BOT_TOKEN'));
$storage = RedisStorage::fromUrl('redis://localhost:6379');
$dispatcher = new Dispatcher(
    storage: $storage,
    fsmStrategy: FsmStrategy::UserInChat,
);
$dispatcher->runPolling(new PollingOptions(), $bot);
```

`RedisStorage::fromUrl` accepts any URI accepted by
`Amp\Redis\RedisConfig::fromUri` (`redis://`, `tcp://`, `unix://`).

### Event isolation

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
