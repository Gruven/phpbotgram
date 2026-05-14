#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Without dispatcher — port of upstream `aiogram/examples/without_dispatcher.py`.
 *
 * What this demonstrates:
 *   - Using `Bot` directly (no `Dispatcher`) with raw `getUpdates` polling.
 *   - Building and dispatching `TelegramMethod` objects manually via `$bot(...)`.
 *   - Long-poll loop with offset tracking for deduplication.
 *   - `Bot` as a thin HTTP client — no router, no middlewares, no FSM.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/without_dispatcher.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Methods\SendMessage;

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
    fwrite(STDERR, "BOT_TOKEN env var is required.\n");
    exit(1);
}

$bot = new Bot($token);
$offset = null;

fwrite(STDOUT, "Without-dispatcher bot starting (Ctrl+C to stop)...\n");

while (true) {
    // Raw getUpdates — no dispatcher involved.
    $updates = $bot(new GetUpdates(
        offset: $offset,
        timeout: 10,
    ));

    foreach ($updates as $update) {
        $offset = $update->updateId + 1;

        $message = $update->message;
        if ($message === null || $message->text === null) {
            continue;
        }

        $chatId = $message->chat->id;
        $text = $message->text;

        fwrite(STDOUT, "Received from chat {$chatId}: {$text}\n");

        // Send a reply directly — no answer() shortcut needed.
        $bot(new SendMessage(
            chatId: $chatId,
            text: "You said: {$text}",
        ));
    }
}
