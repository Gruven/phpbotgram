# Plug in a custom storage backend

## When to use this

You already run a database that isn't Redis or MongoDB — DynamoDB, PostgreSQL, your own KV — and you want FSM state stored there. Extend `BaseStorage` and provide the five abstract methods.

## Solution

```php
use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use Gruven\PhpBotGram\Fsm\State;

final class PdoStorage extends BaseStorage
{
    public function __construct(private readonly PDO $db) {}

    public function setState(StorageKey $key, null|State|string $state = null): void
    {
        $serialised = $state instanceof State ? $state->state() : $state;
        $stmt = $this->db->prepare('REPLACE INTO fsm_state(k, v) VALUES (?, ?)');
        $stmt->execute([(string)$key, $serialised]);
    }

    public function getState(StorageKey $key): ?string { /* SELECT v FROM fsm_state */ }
    public function setData(StorageKey $key, array $data): void { /* JSON encode */ }
    public function getData(StorageKey $key): array { /* JSON decode or [] */ }
    public function close(): void { /* no-op for PDO */ }
}

$dispatcher = new Dispatcher(storage: new PdoStorage($pdo));
```

A subclass of [`BaseStorage`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Storage-BaseStorage.html) must implement five methods: `setState`, `getState`, `setData`, `getData`, `close`. `getValue` and `updateData` come for free from the base — they read-merge-write. Override `updateData` if your store supports atomic field updates.

## Pitfalls

- The `getData` contract is "empty array when nothing stored", not `null`. Callers `array_key_exists` against the return — `null` breaks the FSM.
- `setState(null)` clears — implementations must delete or null the row, not store the literal string `"null"`.
- The default `updateData` is read-merge-write and racy under concurrency. Override it with a single atomic statement if your store allows. See [FSM](../concepts/fsm.md) for the storage key format.
