# Add a custom filter

## When to use this

Built-in filters (`Command`, `F`, `MagicData`, `StateFilter`) cover
most routes, but a project-specific rule — "tag the message as VIP if
the sender id is in our allow-list", "match only photos with a
caption" — wants its own predicate. Subclass `Filter` and return a
plain `bool`.

## Solution

```php
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Message;

final class ContainsHelloFilter extends Filter
{
    public function __invoke(object $event, mixed ...$kwargs): array|bool
    {
        if (!$event instanceof Message) {
            return false;
        }

        $text = strtolower($event->text ?? '');

        return str_contains($text, 'hello');
    }
}

$dispatcher->message->register(
    static function (Message $event): void {
        $event->answer('Hello back!')->emit();
    },
    filters: [new ContainsHelloFilter()],
);
```

A custom filter extends
[`Filter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Filter.html)
and implements `__invoke(object $event, mixed ...$kwargs): array|bool`.
Return `true` to accept, `false` to reject, or an `array<string, mixed>`
to inject kwargs into the handler (see the kwargs recipe).

## Pitfalls

- The `$event` argument is `object` — narrow with `instanceof` before
  reading typed properties. A `message` filter wired onto a
  `callback_query` observer will see the wrong type otherwise.
- Filters are pure predicates — no API calls, no state mutation. Side
  effects belong in middleware. See
  [Filters](../concepts/filters.md) for the contract.
- The variadic `$kwargs` is the running bag from upstream filters and
  middleware. Always declare it variadic; positional kwargs are not
  supported.
