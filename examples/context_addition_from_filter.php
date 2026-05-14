#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Context addition from filter — port of upstream
 * `aiogram/examples/context_addition_from_filter.py`.
 *
 * What this demonstrates:
 *   - A Filter that returns `array<string, mixed>` to inject additional keys
 *     into the dispatcher kwargs bag flowing into subsequent filters and the
 *     handler itself (the "context enrichment" pattern).
 *   - Dispatcher::workflowData for bot-wide defaults shared across all handlers.
 *   - Handler receives the injected `db_connection` and `current_user` kwargs
 *     as named parameters — no global state needed.
 *
 * # Parameter naming
 *
 * `CallableObject::prepareKwargs()` performs a strict `array_intersect_key`
 * between the kwargs bag and the closure's declared parameter names — there
 * is no snake_case ↔ camelCase translation. The filter return keys, the
 * `workflowData` keys, and the handler parameter names must therefore match
 * literally. This example uses snake_case throughout (the upstream Python
 * convention) so the wire-name maps stay obvious.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/context_addition_from_filter.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Message;

/**
 * Simulates resolving a "current user" object from the event's sender.
 *
 * In a real bot this would query a database; here we return a plain array.
 * The returned array is merged into the handler kwargs bag so downstream
 * handlers can declare `array $currentUser` as a parameter.
 */
final class UserResolverFilter extends Filter
{
    public function __invoke(object $event, mixed ...$kwargs): array|bool
    {
        if (!$event instanceof Message) {
            return false;
        }

        $from = $event->fromUser;
        if ($from === null) {
            return false;  // no sender, reject
        }

        // Simulate DB lookup — inject resolved data into the kwargs bag.
        return [
            'current_user' => [
                'id' => $from->id,
                'name' => trim(($from->firstName ?? '') . ' ' . ($from->lastName ?? '')),
                'is_premium' => (bool)($from->isPremium ?? false),
            ],
        ];
    }
}

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
    fwrite(STDERR, "BOT_TOKEN env var is required.\n");
    exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

// workflow_data injects a shared "db_connection" across all handlers.
// (Here it's a placeholder; replace with a real PDO/Redis/etc. instance.)
$dispatcher->workflowData['db_connection'] = 'pdo:sqlite::memory:';

// The handler receives `$current_user` injected by UserResolverFilter,
// and `$db_connection` from $dispatcher->workflowData. Names must match
// literally: see the docblock at the top of this file for why.
$dispatcher->message->register(
    static function (
        Message $event,
        array $current_user,
        string $db_connection,
    ): void {
        $name = $current_user['name'] ?: 'stranger';
        $premium = $current_user['is_premium'] ? ' (Premium)' : '';
        $event->answer(
            "Hello, {$name}{$premium}!\n"
            . "DB: {$db_connection}"
        )->emit();
    },
    filters: [new UserResolverFilter()],
);

// Fallback for anonymous/channel messages.
$dispatcher->message->register(static function (Message $event): void {
    $event->answer("Could not resolve your user profile.")->emit();
});

fwrite(STDOUT, "Context-addition-from-filter bot starting...\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
