# Filters

A filter is a dispatch-time predicate that votes on whether a handler should run. Returns `false` to reject, `true` to accept, or an associative array to accept and merge kwargs into the handler call.

## How it works

### Writing a custom filter

[`Filter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Filter.html) is the abstract base. Every built-in filter ([`Command`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Command.html), [`StateFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-StateFilter.html), [`CallbackQueryFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-CallbackQueryFilter.html), [`ChatMemberUpdatedFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-ChatMemberUpdatedFilter.html), the F-DSL bridges, the logic combinators) inherits from it and implements `__invoke(object $event, mixed ...$kwargs)`.

The variadic `$kwargs` parameter is deliberate: it signals to `CallableObject::prepareKwargs` that the filter wants the *entire* dispatcher kwargs bag (`bot`, `state`, `event_context`, …), not just the keys it literally declares. Without the variadic the prepare step intersects the bag against the parameter names and drops everything else — which would silently break filters that need contextual data the dispatcher injected.

The following filter accepts messages containing the word "hello" and injects a `greeting` kwarg into the handler (adapted from `examples/own_filter.php`):

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
        if (!str_contains($text, 'hello')) {
            return false;
        }
        return ['greeting' => 'Hello, friend!'];
    }
}
```

### Registering a handler with filters

Pass filter instances to the `filters:` named argument of `register`. The handler only runs when every filter in the list accepts:

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Types\Message;

$bot = new Bot('token');
$dispatcher = new Dispatcher();

$dispatcher->message->register(
    static function (Message $event, string $greeting): void {
        $event->answer($greeting)->emit();
    },
    filters: [new ContainsHelloFilter()],
);

$dispatcher->message->register(static function (Message $event): void {
    $event->answer("Try saying hello!")->emit();
});
```

The `$greeting` parameter arrives because `ContainsHelloFilter` returned `['greeting' => 'Hello, friend!']`. The dispatcher merges every filter's array return into the kwargs bag before calling the handler.

### Combining filters

Filters compose with three static helpers.

`Filter::all($f1, $f2)` builds an [`AndFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Logic-AndFilter.html) that cascades kwargs forward — if the first filter returns `['command' => $cmd]`, the second filter sees it in `$kwargs`. `Filter::any($f1, $f2)` builds an [`OrFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Logic-OrFilter.html) where the first accepting child wins, no cascade. `Filter::invertOf($f)` builds an [`InvertFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Logic-InvertFilter.html) that flips the accept/reject decision.

```php
use const Gruven\PhpBotGram\F;
use Gruven\PhpBotGram\Filters\Filter;

// ContainsHelloFilter is the custom filter from above; combine it with
// any other Filter — here an inline F-DSL filter that matches "bye".
$saysBye = F->text->contains('bye')->asFilter();

$andFilter = Filter::all(new ContainsHelloFilter(), $saysBye);
$orFilter  = Filter::any(new ContainsHelloFilter(), $saysBye);
$notFilter = Filter::invertOf(new ContainsHelloFilter());
```

PHP cannot overload `&`/`|`/`~`, so we ship method forms — the result composes identically to aiogram's `f1 & f2 | ~f3` syntax. The static-helper form is more verbose than operator overloading but reads identically once you're used to it.

### Return-value semantics

The return-value semantics mirror aiogram's `HandlerObject.check`: `false` rejects (later filters on the same handler are not consulted), `true` accepts with no kwargs, and an associative array accepts with merged kwargs. The `Command` filter returns `['command' => CommandObject(...)]` so the handler can declare `function (Message $event, CommandObject $command)` and receive the parsed pieces. Other filters that need to expose extracted state (Regex captures, F-DSL selections) follow the same shape. The `null` return is treated as `false` for backward-compat with filters that explicitly return nothing on rejection.

### Global vs per-handler filters

Filters run in two places. **Global filters** registered on the observer apply *before* any handler is considered — one rejection short-circuits the entire chain. **Per-handler filters** passed to `register($cb, filters: [...])` apply for that handler only. Scenes use global filters to scope every handler on a scene's observer to the current FSM state; user code typically uses per-handler filters for command matching, callback prefix routing, etc. Global filters are appended via [`$observer->filter(...)`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Event-TelegramEventObserver.html#method_filter) — e.g. `$dispatcher->message->filter(...)`.

### Exception-typed filters

The exception-typed filters ([`ExceptionTypeFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-ExceptionTypeFilter.html), [`ExceptionMessageFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-ExceptionMessageFilter.html)) are designed for the `errors` observer. They inspect the synthetic `ErrorEvent`'s `exception` slot and accept or reject by type or by `getMessage()` regex. This is how a global error handler can discriminate between "log everything" and "alert on `TelegramRetryAfter` specifically".

## Trade-offs

Filter results are not cached. A filter that hits Redis or runs a DB query runs on every event, every handler. For expensive checks, either attach the policy as a middleware (which runs once per observer) or move the heavy work behind an in-memory cache. The framework does not try to be clever — it would have to invalidate the cache on too many events. Caching the *output* of a filter against the input event would require the filter to be a pure function of the event alone, which is not the contract (filters often read dispatcher kwargs too).

Throws from a filter propagate. There is no automatic "a filter raised therefore reject" rescue. This is deliberate: a filter raising during a database lookup is a bug, not a vote, and swallowing it would mask the failure. If you want a graceful-fallback filter, catch inside the filter's `__invoke` and return `false`:

```php
use Gruven\PhpBotGram\Filters\Filter;

final class SafeDbFilter extends Filter
{
    public function __invoke(object $event, mixed ...$kwargs): array|bool
    {
        try {
            // ...expensive lookup...
            return ['user' => null]; // resolved user object
        } catch (\Throwable) {
            return false; // reject gracefully rather than surface the error
        }
    }
}
```

The `Command` filter has one specific exception: failures inside `$bot->getMe()` (the username lookup for mention-matching) are absorbed so unit tests that don't seed a `getMe` response can still exercise the filter.

Filters are stateless by design. Aiogram's `magic_filter` is expression-tree, not callback-graph, and we keep that model — the F-DSL chain you build with `F->message->text->equals('hi')` resolves fresh against each event. Sharing state across calls (e.g. caching the last seen value) is intentionally hard. If you need that, you want a middleware, not a filter — middlewares get one instance per registration and can hold state on `$this`.

The variadic-kwargs convention is unusual but load-bearing. Removing it would mean every filter has to declare every kwarg it needs by name, and a new dispatcher-injected key would force every filter to update its signature. The variadic shape is invisible at the call site (handlers don't see it) and only matters when authoring new `Filter` subclasses.

## See also

- [F-DSL](f-dsl.md)
- [CallbackData](callback-data.md)
- [API reference: Filter](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Filter.html)
- [API reference: Command](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Command.html)
- [API reference: StateFilter](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-StateFilter.html)
