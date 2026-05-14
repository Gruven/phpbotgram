#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Specify allowed updates — port of upstream `aiogram/examples/specify_updates.py`.
 *
 * What this demonstrates:
 *   - Passing an explicit `allowedUpdates` list to `PollingOptions` to tell
 *     Telegram which update types to deliver.
 *   - This reduces traffic and improves bot startup time when only a subset of
 *     update types is needed.
 *   - Handlers registered for types NOT in the list will never fire.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/specify_updates.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Message;

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
    fwrite(STDERR, "BOT_TOKEN env var is required.\n");
    exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

// Only subscribe to messages and callback queries — nothing else.
$pollingOptions = new PollingOptions(
    allowedUpdates: ['message', 'callback_query'],
);

$dispatcher->message->register(static function (Message $event): void {
    $text = $event->text ?? '';
    $event->answer("You sent a message: {$text}")->emit();
});

$dispatcher->callbackQuery->register(static function (CallbackQuery $event): void {
    $data = $event->data ?? '';
    $event->answer("Callback query data: {$data}")->emit();
});

fwrite(STDOUT, "Specify-updates bot starting (message + callback_query only)...\n");
$dispatcher->runPolling($pollingOptions, $bot);
