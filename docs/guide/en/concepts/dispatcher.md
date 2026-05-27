# Dispatcher

The dispatcher is the heart of a phpbotgram bot. It owns the polling loop, the update-type observer map, and the router cascade.

## How it works

[`Dispatcher`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html) extends `Router`, so the same registration API (`->message->register`, `->callbackQuery->register`, …) is available at the top level. When you call `runPolling`, the dispatcher opens an [`AmphpSession`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-AmphpSession.html) on the bot, then enters a fiber that calls `getUpdates` in a loop. Each returned update is fed through `feedUpdate`, which walks the 25-observer map and resolves the correct observer (`message`, `callbackQuery`, `chatMember`, etc.) by attribute presence on the incoming `Update` payload.

For each observer the dispatcher applies the global filter chain, then per-handler filters, then enters the middleware stack (`outerMiddleware` → handler → `innerMiddleware`). The handler's return value is ignored; side effects ([`$event->answer(...)`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-Message.html#method_answer)) are the contract.

The 25 observers map one-to-one onto Telegram's `Update` fields: `message`, `editedMessage`, `channelPost`, `editedChannelPost`, `businessConnection`, `businessMessage`, `editedBusinessMessage`, `deletedBusinessMessages`, `messageReaction`, `messageReactionCount`, `inlineQuery`, `chosenInlineResult`, `callbackQuery`, `shippingQuery`, `preCheckoutQuery`, `purchasedPaidMedia`, `poll`, `pollAnswer`, `myChatMember`, `chatMember`, `chatJoinRequest`, `chatBoost`, `removedChatBoost`, plus the synthetic `errors` and `update` observers. Resolving by attribute presence means a single `getUpdates` poll can fan out into multiple handlers on the same update — for instance, the `update` observer always fires alongside the type-specific one.

Routing inside the cascade is depth-first. The dispatcher itself is the root [`Router`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Router.html); included child routers (`$dispatcher->includeRouter($shopRouter)`) form a tree. A handler match in a deeper router short-circuits the walk; a no-match continues up the tree. Filter resolution uses fiber-local context so each update has its own observer / router / handler stack without thread-safety concerns.

The polling-loop shape is straightforward:

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;

$bot = new Bot(getenv('BOT_TOKEN'));
$dispatcher = new Dispatcher();
// register handlers on $dispatcher->message, $dispatcher->callbackQuery, …
$dispatcher->runPolling(new PollingOptions(), $bot);
```

The variadic `Bot ...$bots` parameter lets a single dispatcher drive several bot tokens from one process — handy for shared admin UIs or for fanning out an alerting service across customer-specific tokens.

Graceful shutdown: the dispatcher registers `SIGINT`/`SIGTERM` handlers on `runPolling`. On signal it stops fetching new updates, lets in-flight handlers finish, and exits with code 0. This means production bots running under systemd can be restarted without losing updates already delivered to the loop. `pcntl` is required — on builds without it the dispatcher swallows the unsupported-feature exception and falls back to "exit immediately on Ctrl-C" semantics.

## Trade-offs

The dispatcher is the *only* update-fetching entry point in the framework. Webhook mode also goes through `feedUpdate`, just from a different fiber driven by `amphp/http-server` instead of the polling loop. The duplication aiogram has (`Dispatcher` vs. `Bot.start_webhook`) is collapsed; this trades flexibility (you can't have a separate "command bot" object that bypasses the router cascade) for a single source of truth and a single integration point for outer middlewares.

`runPolling` is blocking. If you need to mix the bot with other amphp services in the same fiber, use the lower-level `startPolling` which returns an `amphp/future` you can join with the rest of the loop. The blocking variant is the recommended default because production bots almost always *are* the only thing in their process.

Backoff on `TelegramRetryAfter` is built into the polling loop rather than living in the session. The trade-off is that the session can't self-throttle on its own; bots that need rate limiting must compose [`Backoff`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-Backoff.html) into their own send paths or reach for the [`CallbackAnswerMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-CallbackAnswer-CallbackAnswerMiddleware.html) and [`ChatActionSender`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-ChatAction-ChatActionSender.html) helpers when the use-case matches. See the [rate-limiting recipe](../how-to/rate-limiting.md) for the cookbook version.

A handful of decisions are deliberately tight to keep the runtime predictable: the dispatcher does not spawn worker fibers for each update (handlers run inline in the polling fiber), there is no priority queue between observers (registration order is the only sort), and `feedUpdate` does not retry on handler exceptions (`errors` observer is the only failure surface). Bots that need worker-pool semantics can drive their own queue in front of the dispatcher; the framework deliberately ships only the simple shape.

## See also

- [Routers](routers.md)
- [Middlewares](middlewares.md)
- [Error model](error-model.md)
- [API reference: Dispatcher](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html)
- [API reference: PollingOptions](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-PollingOptions.html)
