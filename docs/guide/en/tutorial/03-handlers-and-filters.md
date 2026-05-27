# Handlers and filters

A handler is a closure registered on an event observer. A filter is a callable that votes on whether a handler should run. This lesson adds a `/start` welcome and a `/help` listing.

## Add a Command filter

```php
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Types\Message;

$dispatcher->message->register(
    static function (Message $event): void {
        $event->answer('Welcome! Send any message and I will echo it.')->emit();
    },
    filters: [new Command('start')],
);
```

The `filters: [...]` array list controls when the handler fires. phpbotgram accepts any [callable](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Filter.html) here — bare `Filter` instances (like `Command`) are wrapped in a closure automatically.

## The F-DSL

For filters on a field of the event, the `F` constant lets you write chain expressions:

```php
use Gruven\PhpBotGram\Filters\Filter;
use const Gruven\PhpBotGram\F;

$dispatcher->message->register(
    static function (Message $event): void {
        $event->answer('You sent a private message.')->emit();
    },
    filters: [
        Filter::all(
            new Command('start'),
            F->chat->type->equals('private')->asFilter(),
        ),
    ],
);
```

`Filter::all(...)` builds a logical AND across filters. `Filter::any(...)` builds OR; `Filter::invertOf($f)` negates.

## Filters that inject kwargs

A filter can return an associative array; entries get merged into the handler's named arguments. This is how `CallbackData` and `Command` pass parsed data through.

```php
$dispatcher->message->register(
    static function (Message $event, string $user_id): void {
        $event->answer("user_id=$user_id")->emit();
    },
    filters: [
        static fn (Message $e): array => ['user_id' => (string)$e->chat->id],
    ],
);
```

The handler closure must declare a parameter with the literal name `$user_id` (no snake↔camel translation; the framework uses strict `array_intersect_key`).

## Next step

[Add multi-step state →](04-state.md)
