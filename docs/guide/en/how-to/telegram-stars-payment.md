# Sell something via Telegram Stars

## When to use this

Telegram Stars (`XTR`) let you charge in-platform without a payment
processor — useful for premium features, content unlocks, or
small-amount digital goods. The flow is invoice → pre-checkout
confirm → successful payment notification.

## Solution

```php
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Methods\AnswerPreCheckoutQuery;
use Gruven\PhpBotGram\Methods\SendInvoice;
use Gruven\PhpBotGram\Types\LabeledPrice;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\PreCheckoutQuery;

// 1. /buy → send the invoice.
$dispatcher->message->register(
    static function (Message $event, Bot $bot): void {
        $bot(new SendInvoice(
            chatId: $event->chat->id,
            title: 'Premium feature',
            description: 'Unlock the premium feature.',
            payload: 'premium_feature_payload',
            currency: 'XTR',
            prices: [new LabeledPrice(label: 'Premium', amount: 1)],
        ));
    },
    filters: [new Command('buy')],
);

// 2. Confirm the pre-checkout query (Telegram waits 10 seconds).
$dispatcher->preCheckoutQuery->register(static function (PreCheckoutQuery $event, Bot $bot): void {
    $bot(new AnswerPreCheckoutQuery(preCheckoutQueryId: $event->id, ok: true));
});

// 3. Successful payment fires as a message subtype.
$dispatcher->message->register(static function (Message $event): void {
    if ($event->successfulPayment !== null) {
        $event->answer('Thanks! Premium unlocked.')->emit();
    }
});
```

[`SendInvoice`](https://api.phpbotgram.local/Gruven-PhpBotGram-Methods-SendInvoice.html)
with `currency: 'XTR'` opens the Stars purchase flow. The
`preCheckoutQuery` observer MUST answer within 10 seconds or Telegram
cancels the transaction — keep that handler synchronous. After the
user pays, a `Message` arrives with a non-null `successfulPayment`.

## Pitfalls

- For Stars, `amount` is the literal Star count — 1 = 1 Star. Other
  currencies use the smallest unit (cents, kopecks), so don't hard-code
  100x multipliers.
- The bot owner must enable payments via @BotFather before any of this
  works — the framework gives no clearer error than the API's "PAYMENT_PROVIDER_INVALID".
- The `payload` round-trips through Telegram unchanged — sign or
  encrypt it if you'd be unhappy with the user editing it.
