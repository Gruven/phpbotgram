# Middlewares

A middleware wraps the dispatch of a handler. It receives the next
link, the event, and the kwargs bag — invoking the next link delegates,
skipping it short-circuits.

## How it works

[`BaseMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Middlewares-BaseMiddleware.html)
is an abstract one-method class: `__invoke(Closure $handler, object
$event, array $data): mixed`. Concrete subclasses run code before
delegating to `$handler($event, $data)`, after the delegate returns, or
both. Returning without invoking `$handler` cancels the dispatch —
useful for throttling, auth gates, or cache hits. The `$event` is
typed as `object` rather than `TelegramObject` so the same chain can
transport the dispatcher-synthetic `ErrorEvent`, which deliberately
does not extend `TelegramObject` (see [Error model](error-model.md)).

There are two attachment points per observer. `outerMiddleware` wraps
the *whole* observer — global filter chain plus handler iteration plus
sub-router recursion. `innerMiddleware` wraps each *individual* handler
invocation. The split matters: outer middlewares run once per event,
inner middlewares run once per handler-call. A throttling middleware
typically goes on `outer` (you want the gate to fire before filters
even run); a logging-per-handler middleware goes on `inner`. The
[`MiddlewareManager`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Middlewares-MiddlewareManager.html)
class owns both lists and provides the `wrap()` primitive that
composes a terminal closure with the registered links.

The dispatcher pre-wires three middlewares automatically.
[`UserContextMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Middlewares-UserContextMiddleware.html)
injects `event_context`, `event_from_user`, `event_chat`, and
`event_thread_id` into the kwargs bag so every handler sees the same
context shape. [`ErrorsMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Middlewares-ErrorsMiddleware.html)
catches handler throws and re-dispatches them through the `errors`
observer. `FsmContextMiddleware` (when FSM is enabled, which is the
default) materialises an
[`FsmContext`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmContext.html)
and injects `state`, `raw_state`, and `fsm_storage` keys. These three
wire in at construction; user middlewares stack above them. The order
matters: `UserContextMiddleware` is first so subsequent links see the
canonical context keys populated, `ErrorsMiddleware` is second so its
catch wraps user-context resolution.

Inner middlewares compose along the router chain. When a leaf router's
observer dispatches, its `resolveMiddlewares()` walks parent-to-root
and collects every ancestor router's matching inner-middleware stack.
The root's middlewares end up outermost in the composed chain — so a
tenant-scope middleware attached to the dispatcher wraps a logging
middleware attached to a sub-router, which wraps the leaf handler.
This is the same shape aiogram has via `chain_head`; the PHP port
walks `parentRouter` references because that's idiomatic and avoids
the implicit MRO Python-style introspection.

The dispatcher-level middleware chain (the wiring of `UserContext` +
`Errors`) is attached at the *ingress* in `Dispatcher::feedUpdate`,
not on every observer at construction. Wrapping at ingress avoids the
double-wrap regression a per-observer approach would cause on
multi-router trees — see Fix C1 in the dispatcher source for the
history. The composition runs exactly once per dispatch, around the
terminal `propagateEvent` call.

## Trade-offs

Two attachment points means two places to remember when something
isn't firing. The naming is deliberate — outer wraps the observer,
inner wraps the handler — but the conceptual model is a layered
matryoshka, not a list. The pilot pass deliberately documented this
split rather than collapsing it: aiogram's `outer_middlewares` /
`middlewares` split made the same trade-off and we kept it for parity.
Collapsing them would have made one of the two use cases awkward, and
neither subset of users would have been better off.

Middlewares cannot replace the event mid-flight. The `$event` argument
is positional and the next link sees whatever you forward — but the
event observer map has already resolved the update type by the time
the middleware runs. Transforming an `EditedMessage` into a `Message`
mid-middleware is technically possible but defeats the dispatch
contract and is not supported. Use distinct handlers per type
instead. The framework does not type-guard against this kind of misuse;
it would clutter the middleware API without preventing the underlying
mistake.

The middleware contract is sync-style. Implementations may suspend
internally (an FSM lookup against Redis is one fiber suspend) but the
public surface returns a concrete value, never a `Future`. This keeps
handler signatures clean — a handler never has to `->await()` its
middlewares — at the cost of one extra event-loop tick per suspending
middleware. For non-suspending middlewares the cost is zero. The
sync-style is the same trade the rest of the framework makes; see
[Architecture decisions](architecture-decisions.md).

Custom middlewares cannot bypass the dispatcher's wired chain. The
three pre-wired middlewares always run; you can append above them but
not before. This is intentional — `UserContextMiddleware` is the
source of `event_context`, and skipping it would break every other
middleware. If you need to run code earlier than the wired chain, the
session's request middleware is the right hook for outbound work; for
inbound work, run from inside an outer middleware and accept that
`event_context` is already populated.

## See also

- [Dispatcher](dispatcher.md)
- [Flags](flags.md)
- [API reference: BaseMiddleware](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Middlewares-BaseMiddleware.html)
- [API reference: MiddlewareManager](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Middlewares-MiddlewareManager.html)
- [API reference: ErrorsMiddleware](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Middlewares-ErrorsMiddleware.html)
