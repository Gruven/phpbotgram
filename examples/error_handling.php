#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Error handling — port of upstream `aiogram/examples/error_handling.py`.
 *
 * What this demonstrates:
 *   - Registering a handler on `$dispatcher->errors` to catch exceptions
 *     thrown by regular handlers.
 *   - ExceptionTypeFilter: route errors by exception class.
 *   - ErrorEvent carries both the original Update and the Throwable.
 *   - Errors observer falls back to re-raising when no handler claims the event.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/error_handling.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Exceptions\TelegramApiException;
use Gruven\PhpBotGram\Filters\ExceptionTypeFilter;
use Gruven\PhpBotGram\Types\ErrorEvent;
use Gruven\PhpBotGram\Types\Message;

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
    fwrite(STDERR, "BOT_TOKEN env var is required.\n");
    exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

// Normal handler that intentionally throws a RuntimeException.
$dispatcher->message->register(static function (Message $event): void {
    $text = $event->text ?? '';
    if ($text === '/boom') {
        throw new \RuntimeException('Intentional boom triggered by /boom command');
    }
    $event->answer("You said: {$text}")->emit();
});

// Error handler: catches only RuntimeException.
$dispatcher->errors->register(
    static function (ErrorEvent $event): void {
        $msg = $event->exception->getMessage();
        fwrite(STDOUT, "[error-handler] Caught RuntimeException: {$msg}\n");

        // Attempt to notify the user if the update carries a message.
        $message = $event->update->message;
        if ($message !== null) {
            $message->answer("Oops! Something went wrong: {$msg}")->emit();
        }
    },
    filters: [new ExceptionTypeFilter(\RuntimeException::class)],
);

// Error handler: catches TelegramApiException (e.g. chat not found).
$dispatcher->errors->register(
    static function (ErrorEvent $event): void {
        $msg = $event->exception->getMessage();
        fwrite(STDOUT, "[error-handler] Caught TelegramApiException: {$msg}\n");
    },
    filters: [new ExceptionTypeFilter(TelegramApiException::class)],
);

fwrite(STDOUT, "Error-handling bot starting...\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
