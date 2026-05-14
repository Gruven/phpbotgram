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

// Blocks until the server is stopped (SIGTERM/SIGINT).
AmphpServer::run(
    handler: $handler,
    dispatcher: $dispatcher,
    host: '0.0.0.0',
    port: 8080,
    path: '/webhook',
);
