# Acknowledge callback queries cleanly

## When to use this

Every inline-button tap fires a `callback_query` that you MUST answer within ~15 seconds or the button spins forever. Installing the `CallbackAnswerMiddleware` once answers every query automatically — post-handler by default, pre-handler when you want instant feedback.

## Solution

```php
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswer;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswerMiddleware;

// Install once on the dispatcher (or any router).
$dispatcher->callbackQuery->middleware(new CallbackAnswerMiddleware());

// Normal handler — no manual answer call needed.
$dispatcher->callbackQuery->register(static function (CallbackQuery $event): void {
    $event->message?->editText('Order confirmed')->emit();
});

// Per-handler override: show a popup BEFORE the handler runs.
$dispatcher->callbackQuery->register(
    static function (CallbackQuery $event, CallbackAnswer $callback_answer): void {
        // Run slow work; the user already saw "Processing…".
        $callback_answer->disabled = true;  // skip the auto-answer
    },
    flags: ['callback_answer' => ['pre' => true, 'text' => 'Processing…']],
);
```

[`CallbackAnswerMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-CallbackAnswer-CallbackAnswerMiddleware.html) injects a [`CallbackAnswer`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-CallbackAnswer-CallbackAnswer.html) DTO into every handler. Post-mode (default) answers after the handler returns or throws; pre-mode answers first and treats the handler as "in flight". Per-handler `flags: ['callback_answer' => […]]` override the defaults.

## Pitfalls

- Setting `disabled = true` makes you responsible for calling `answerCallbackQuery` yourself. Forgetting both leaves the button spinning.
- Pre-mode and post-mode are mutually exclusive per handler — the middleware never double-answers.
- Errors in the handler still trigger the post-mode answer (the middleware uses a `finally` block). The `errors` observer sees the exception second. See [Middlewares](../concepts/middlewares.md) for the order.
