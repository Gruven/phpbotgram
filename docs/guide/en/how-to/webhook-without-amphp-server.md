# Serve webhooks without amphp/http-server

## When to use this

You already run nginx + php-fpm, RoadRunner, FrankenPHP, or any
single-request PHP runtime — you don't want a second event-loop
server next to it. `feedWebhookUpdate` accepts an `Update` directly,
so any HTTP entry point can dispatch to the same handlers.

## Solution

```php
// public/webhook.php
require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;

$bot = new Bot(getenv('BOT_TOKEN'));
$dispatcher = require __DIR__ . '/../bootstrap.php';   // returns Dispatcher

$expected = getenv('WEBHOOK_SECRET');
$received = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if ($expected !== '' && !hash_equals($expected, $received)) {
    http_response_code(401);
    exit;
}

$body = file_get_contents('php://input');
$payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

$dispatcher->feedWebhookUpdate($bot, $payload);
http_response_code(200);
```

[`Dispatcher::feedWebhookUpdate`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html)
accepts a raw decoded array or an `Update` instance and runs the
full dispatch chain. For the amphp-http path,
[`SimpleRequestHandler`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-SimpleRequestHandler.html)
already wraps secret-token validation with `hash_equals`; replicate
the comparison manually in non-amphp deployments.

## Pitfalls

- `hash_equals` is constant-time; a `===` comparison leaks timing
  information about the secret. Always use `hash_equals` for header
  checks.
- Single-request runtimes (php-fpm, mod_php) tear down the dispatcher
  per request, so `MemoryStorage` is useless — bind a persistent
  storage (Redis/Mongo/SQL) or FSM state vanishes between updates.
- Telegram retries a 5xx response. If your handler may exceed 60
  seconds, return 200 immediately and process in a queue — see
  [Webhook](../concepts/webhook.md) for the fall-through model.
