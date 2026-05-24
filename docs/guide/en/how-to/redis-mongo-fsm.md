# Use Redis or MongoDB for FSM storage

## When to use this

The default `MemoryStorage` is process-local — it vanishes on
restart, and parallel workers can't see each other's state. Promote
to Redis or MongoDB the moment your bot scales past one fiber on one
host.

## Solution

### Redis

```php
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Fsm\Storage\RedisStorage;

// TTL applies to state and data separately.
$storage = RedisStorage::fromUrl(
    url: 'redis://localhost:6379/0',
    stateTtl: 3600,
    dataTtl: 7200,
);

$dispatcher = new Dispatcher(storage: $storage);
```

### MongoDB

```php
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Fsm\Storage\MongoStorage;

// One document per FSM context.
$storage = MongoStorage::fromUrl(
    url: 'mongodb://localhost:27017',
    database: 'phpbotgram_fsm',
    collectionName: 'states_and_data',
);

$dispatcher = new Dispatcher(storage: $storage);
```

[`RedisStorage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-RedisStorage.html)
serialises state as plain Redis strings and data payloads as JSON.

[`MongoStorage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-MongoStorage.html)
stores a single document per FSM context with `state` and `data`
fields; its `updateData` uses atomic `$set` so concurrent writes don't
race. Both inherit `getValue`/`updateData` from `BaseStorage`.

## Pitfalls

- Redis empty data → key deletion; MongoDB empty data → field
  `$unset`. Reading after a clear returns the empty array, not `null`.
- The `mongodb/mongodb` library is blocking. `MongoStorage` wraps
  every call in `Amp\async()` to keep the event loop responsive;
  using it from a non-fiber context still works but with no concurrency
  benefit.
- TTL applies per-key on Redis but is not enforced on Mongo. Build a
  TTL index manually on `_id` if you need automatic cleanup. See
  [FSM](../concepts/fsm.md) for the storage key model.
