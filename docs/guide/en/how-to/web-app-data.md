# Validate Web App initData

## When to use this

A Telegram Mini App posts `initData` to your backend so you can
identify the user. The string is signed; you MUST verify the
signature before trusting any field. The framework ships two
validators — HMAC (bot-token-based) and Ed25519 (third-party,
public-key-based).

## Solution

```php
use Gruven\PhpBotGram\Utils\WebApp\WebApp;
use Gruven\PhpBotGram\Utils\WebApp\WebAppSignature;

$initData = $_POST['init_data'];        // raw query-string from the Mini App
$botToken = getenv('BOT_TOKEN');
$botId    = (int)explode(':', $botToken)[0];

// Option A — HMAC via the bot token (server-side, single-bot).
try {
    $parsed = WebApp::safeParseInitData($botToken, $initData);
} catch (InvalidArgumentException) {
    http_response_code(403);
    exit('Invalid signature');
}

// Option B — Ed25519 via the Telegram public key (multi-bot, microservices).
if (!WebAppSignature::check($botId, $initData)) {
    http_response_code(403);
    exit('Invalid signature');
}
$parsed = WebApp::parseInitData($initData);
echo "Hello, {$parsed->user->firstName}";
```

[`WebApp::safeParseInitData`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-WebApp-WebApp.html)
runs the HMAC-SHA-256 check against the bot token and returns a
typed
[`WebAppInitData`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-WebApp-WebAppInitData.html).
[`WebAppSignature::check`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-WebApp-WebAppSignature.html)
verifies the `signature` field against the Telegram Ed25519 public
key — pick this when the validating service doesn't have the bot
token.

## Pitfalls

- HMAC requires the bot token; Ed25519 only requires the numeric bot
  ID. Choose by where the validating code runs.
- Both checks fail silently on missing `sodium` (Ed25519) or
  malformed query strings. Always treat `false`/exception as 403.
- Don't reuse `initData` — Telegram allows up to 24 hours but the
  `auth_date` field is yours to enforce. Reject older payloads. See
  [Webhook](../concepts/webhook.md) for the broader Mini App entry
  flow.
