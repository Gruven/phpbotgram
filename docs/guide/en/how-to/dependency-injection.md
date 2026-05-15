# Pass kwargs from a filter to the handler

## When to use this

Resolve per-request data — current user, db handle, parsed payload —
once in a filter and let downstream filters and the handler receive
it as named parameters. This avoids global state and keeps the
handler signature self-documenting.

## Solution

```php
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Message;

final class UserResolverFilter extends Filter
{
    public function __invoke(object $event, mixed ...$kwargs): array|bool
    {
        if (!$event instanceof Message || $event->fromUser === null) {
            return false;
        }

        return [
            'current_user' => [
                'id' => $event->fromUser->id,
                'name' => $event->fromUser->firstName ?? 'stranger',
            ],
        ];
    }
}

$dispatcher->workflowData['db_connection'] = $pdo;

$dispatcher->message->register(
    static function (
        Message $event,
        array $current_user,
        PDO $db_connection,
    ): void {
        $event->answer("Hello, {$current_user['name']}!")->emit();
    },
    filters: [new UserResolverFilter()],
);
```

A filter that returns an array merges its keys into the dispatcher
kwargs bag. `Dispatcher::workflowData` seeds bot-wide defaults. The
[`CallableObject`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Event-CallableObject.html)
binder calls `array_intersect_key` against the closure's parameter
names — no snake_case ↔ camelCase translation, names must match
literally.

## Pitfalls

- The merge is strict by name. Misspelling `$current_user` as
  `$currentUser` silently drops the value to `null` and PHP will
  fatal on a non-nullable parameter. See
  [Dispatcher](../concepts/dispatcher.md) for the resolution order.
- Workflow data and filter returns share the same bag — a filter key
  overrides the workflow default for the rest of the dispatch.
- Variadic `mixed ...$kwargs` in the filter is required: it captures
  the upstream bag so injected keys propagate forward.
