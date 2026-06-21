# Acknowledge callback queries cleanly

## When to use this

Every inline-button tap fires a `callback_query` that you MUST answer within ~15 seconds or the button spins forever. Installing the `CallbackAnswerMiddleware` once answers every query automatically — post-handler by default, pre-handler when you want instant feedback.

## Solution

### Default post-mode answer

```php
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswerMiddleware;

// Install once on the dispatcher (or any router).
$dispatcher->callbackQuery->innerMiddleware(new CallbackAnswerMiddleware());

// Normal handler — no manual answer call needed.
$dispatcher->callbackQuery->register(static function (CallbackQuery $event): void {
    if (!$event->message instanceof Message) {
        return;
    }

    $event->message->editText('Order confirmed')->emit();
});
```

### Per-handler pre-mode answer

Use this handler instead of the catch-all post-mode handler above, or add disjoint filters when both handlers live on the same observer.

```php
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswerMiddleware;

$dispatcher->callbackQuery->innerMiddleware(new CallbackAnswerMiddleware());

$dispatcher->callbackQuery->register(
    static function (CallbackQuery $event): void {
        // Run slow work; the user already saw "Processing…".
    },
    flags: ['callback_answer' => ['pre' => true, 'text' => 'Processing…']],
);
```

[`CallbackAnswerMiddleware`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-CallbackAnswer-CallbackAnswerMiddleware.html) injects a [`CallbackAnswer`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-CallbackAnswer-CallbackAnswer.html) DTO into every handler. Post-mode (default) answers after the handler returns or throws; pre-mode answers first and treats the handler as "in flight". Per-handler `flags: ['callback_answer' => […]]` override the defaults.

The full runnable version is [`examples/inline_keyboard.php`](https://github.com/Gruven/phpbotgram/blob/master/examples/inline_keyboard.php).

## Pitfalls

- Setting `disabled = true` makes you responsible for calling `answerCallbackQuery` yourself. Forgetting both leaves the button spinning.
- Pre-mode answers before the handler runs, so answer fields are frozen by the time handler code sees the injected DTO. Configure pre-mode text/alert/cache via flags.
- Pre-mode and post-mode are mutually exclusive per handler — the middleware never double-answers.
- Errors in the handler still trigger the post-mode answer (the middleware uses a `finally` block). The `errors` observer sees the exception second. See [Middlewares](../concepts/middlewares.md) for the order.
