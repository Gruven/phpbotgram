# Dispatcher

The dispatcher is the heart of a phpbotgram bot. It owns the polling
loop, the update-type observer map, and the router cascade.

## How it works

[`Dispatcher`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html)
extends `Router`, so the same registration API (`->message->register`,
`->callbackQuery->register`, …) is available at the top level. When
you call `runPolling`, the dispatcher opens an
[`AmphpSession`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-AmphpSession.html)
on the bot, then enters a fiber that calls `getUpdates` in a loop. Each
returned update is fed through `feedUpdate`, which walks the
25-observer map and resolves the correct observer
(`message`, `callbackQuery`, `chatMember`, etc.) by attribute presence.

For each observer the dispatcher applies the global filter chain,
then per-handler filters, then enters the middleware stack
(`outerMiddleware` → handler → `innerMiddleware`). The handler's
return value is ignored; side effects (`$event->answer(...)`) are
the contract.

Graceful shutdown: the dispatcher registers `SIGINT`/`SIGTERM`
handlers on `runPolling`. On signal it stops fetching new updates,
lets in-flight handlers finish, and exits with code 0. This means
production bots running under systemd can be restarted without
losing updates already delivered.

## Trade-offs

The dispatcher is the *only* update-fetching entry point in the
framework. Webhook mode also goes through `feedUpdate`, just from
a different fiber. The duplication aiogram has (`Dispatcher` vs.
`Bot.start_webhook`) is collapsed; this trades flexibility (you
can't have a separate "command bot" object) for a single source of
truth.

`runPolling` is blocking. If you need to mix the bot with other
amphp services, use the lower-level `startPolling` and join its
future yourself. See [Webhook](webhook.md) for the long-running
mode.

## See also

- [Routers](routers.md)
- [Middlewares](middlewares.md)
- [API reference: Dispatcher](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html)
- [API reference: PollingOptions](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-PollingOptions.html)
