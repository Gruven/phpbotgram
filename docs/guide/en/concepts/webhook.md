# Webhook

Webhook mode receives Telegram updates as inbound HTTP POSTs instead of
polling for them. The dispatch path is the same as polling — the only
difference is where the `Update` comes from.

## How it works

[`BaseRequestHandler`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-BaseRequestHandler.html)
is the `amphp/http-server` request handler that owns the conversion
from HTTP request to `feedWebhookUpdate`. The default behaviour reads
up to 5 MiB of body, JSON-decodes it, feeds the resulting `Update`
through the dispatcher inside a 55-second deadline, and responds with
`200 OK` + empty JSON. If `handleInBackground` is `true`, the
dispatch is detached into an Amp fiber and the 200 ships immediately
— useful when handlers take longer than Telegram's webhook timeout.
The 5 MiB cap is generous against typical Telegram updates (a few
KiB at most) and prevents an unbounded-buffer DoS from a malicious
client.

Two concrete handlers ship.
[`SimpleRequestHandler`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-SimpleRequestHandler.html)
binds a single `Bot` to the path; every incoming update is dispatched
on that bot.
[`TokenBasedRequestHandler`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-TokenBasedRequestHandler.html)
embeds the bot token in the URL path (`/webhook/{token}`), looks up
the matching bot from a registry, and dispatches accordingly — the
multi-tenant story. Both override the abstract `resolveBot()` hook on
`BaseRequestHandler`; the rest of the request handling (body buffering,
JSON decode, deadline race, response shape) lives in the base class.

The 55-second deadline lives in `Dispatcher::feedWebhookUpdate`.
Telegram closes the webhook connection at 60s; the 5s headroom lets
the HTTP write-back land. When the dispatch finishes before the
deadline, an in-flight `TelegramMethod` returned by a handler can be
serialised as the inline response body, saving a round-trip. When the
deadline expires, the dispatch keeps running in the background and any
eventual `TelegramMethod` is routed through `silentCallRequest` — so
the side effect still reaches Telegram even if the response window
has closed. The race is implemented via `Amp\Future\awaitFirst`
against the dispatch task and a delay timer; the timer task is
`ignore()`'d so a never-awaited timeout (happy path where the
dispatch wins immediately) doesn't surface an "unhandled future"
warning at GC time.

[`IpFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-IpFilter.html)
is the CIDR-based IP allowlist for direct exposure (no reverse proxy
in front). Telegram publishes its source ranges
(`149.154.160.0/20`, `91.108.4.0/22`); the filter rejects everything
else with `403 Forbidden`. The implementation is bitwise-mask on
`(networkLong, prefix)` tuples — O(n) over the typically-tiny
allowlist, n=2 in the default config. For a deployment behind a
reverse proxy (the recommended shape), the filter is unnecessary
because the proxy already enforces network-level access control.

[`Setup`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-Setup.html)
wires the handler into an existing `amphp/http-server` (or any
caller-owned server) and attaches the dispatcher's startup/shutdown
hooks to the server lifecycle. `Setup::register()` accepts a
`callable(string, RequestHandler): void` instead of a concrete router
type — `amphp/http-server-router` is not a project dependency, so we
take any caller's route registration shape and forward through it.
The
[`AmphpServer`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-Server-AmphpServer.html)
wrapper is the one-line "I just want a webhook server running" helper
that constructs the server, binds the path, and joins on shutdown.

## Trade-offs

`amphp/http-server` is a *suggested* dependency, not required.
Composer does not pull it for users on polling-only deployments,
keeping the install footprint small. Webhook users add
`composer require amphp/http-server` themselves — flagged explicitly
in the README quickstart. The cost is one extra `composer require`
for webhook deployments; the benefit is no surprise transitive deps
for everyone else. We considered making it a hard dependency and
rejected it — many production phpbotgram bots run on long-polling
and shouldn't ship an HTTP server they don't use.

The v0 response shape always writes empty JSON `{}`. The webhook-reply
optimisation (returning a `TelegramMethod` as the multipart body to
save a round-trip) is deferred — porting the multipart writer in
aiogram's `aiohttp_server` is non-trivial. The current behaviour is
correct but slightly slower; the optimisation is on the roadmap. When
the dispatch returns a `TelegramMethod`, the dispatcher's
`silentCallRequest` issues the API call as a second request — so the
*effect* is right, just with one extra round-trip.

The 55-second deadline is *per dispatch*, not per handler. A scene
that fans out to multiple sub-handlers shares the budget. If your
handlers do heavy work, set `handleInBackground: true` to disconnect
the dispatch from the HTTP response and let the 200 fly immediately.
The framework will not silently wait beyond 55s on the response path
— the dispatch continues in the background, but the HTTP socket has
already been released by then. For workflows where the response
content matters, design handlers to finish under 55s.

Background mode tracks in-flight fibers in `$backgroundTasks`. On
graceful shutdown the framework awaits these to finish before closing
the bot session; this is how `Setup`'s `onStop` hook avoids cutting
off a handler mid-dispatch. The tracking is automatic and per-handler;
no user code is required.

## See also

- [Dispatcher](dispatcher.md)
- [API reference: BaseRequestHandler](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-BaseRequestHandler.html)
- [API reference: SimpleRequestHandler](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-SimpleRequestHandler.html)
- [API reference: Setup](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-Setup.html)
- [API reference: IpFilter](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-IpFilter.html)
