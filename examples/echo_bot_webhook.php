#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Webhook echo bot — port of upstream `aiogram/examples/echo_bot_webhook.py`.
 *
 * What this demonstrates:
 *   - Webhook delivery via AmphpServer (amphp/http-server v3).
 *   - SimpleRequestHandler wiring: one Bot, one Dispatcher, one path.
 *   - BOT_TOKEN read from environment.
 *   - Dispatcher::startup/shutdown lifecycle hooks over the webhook server.
 *
 * Run (no SSL required — use a reverse-proxy or ngrok in front for HTTPS):
 *   BOT_TOKEN=123:abc php examples/echo_bot_webhook.php
 *
 * Then point Telegram's setWebhook to http://your-host:8080/webhook.
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Webhook\Server\AmphpServer;
use Gruven\PhpBotGram\Webhook\SimpleRequestHandler;

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
    fwrite(STDERR, "BOT_TOKEN env var is required.\n");
    exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

$dispatcher->message->register(static function (Message $event): void {
    $text = $event->text ?? '';
    if ($text === '') {
        return;
    }
    $event->answer($text)->emit();
});

$dispatcher->startup->register(static function (): void {
    fwrite(STDOUT, "Webhook echo bot starting...\n");
});

$dispatcher->shutdown->register(static function (): void {
    fwrite(STDOUT, "Webhook echo bot stopped.\n");
});

$handler = new SimpleRequestHandler(
    dispatcher: $dispatcher,
    bot: $bot,
);

// `host: '127.0.0.1'` is the safe default: the bot listens only on the
// loopback interface and expects a reverse proxy (e.g. the nginx config
// at `deploy/nginx/phpbotgram-webhook.conf`) to terminate TLS and forward
// authenticated traffic. Change to `'0.0.0.0'` ONLY if you intentionally
// expose the bot directly to the public internet (rare; Telegram requires
// HTTPS, so you would also need a TLS-terminating wrapper).
//
// `AmphpServer::run` returns the started `SocketHttpServer`. The amphp
// runtime keeps processing requests until something stops the server —
// typical paths are:
//   - SIGTERM / SIGINT from the OS (the runtime will exit; the server's
//     `onStop` hook flushes background tasks and emits the dispatcher
//     shutdown observer);
//   - explicit `$server->stop()` from inside another fiber for graceful
//     drain (e.g. inside a `$dispatcher->shutdown->register(...)` body).
// Capture the return value if you need either path; this minimal echo
// example lets the OS handle termination.
AmphpServer::run(
    handler: $handler,
    dispatcher: $dispatcher,
    host: '127.0.0.1',
    port: 8080,
    path: '/webhook',
);
