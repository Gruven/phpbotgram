#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Multiple bots — port of upstream `aiogram/examples/multibot.py`.
 *
 * What this demonstrates:
 *   - Running multiple `Bot` instances against the SAME Dispatcher.
 *   - `startPolling(PollingOptions, $bot1, $bot2, ...)` spawns one polling
 *     fiber per bot; all share one handler tree.
 *   - The injected `Bot $bot` kwarg lets handlers identify WHICH bot received
 *     the current update.
 *
 * Run:
 *   BOT_TOKEN=123:abc BOT_TOKEN_2=456:def php examples/multibot.php
 *
 * Both bots must be created with @BotFather and their tokens exported.
 * If BOT_TOKEN_2 is absent the example falls back to a single bot.
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

$token1 = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token1 === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$token2 = getenv('BOT_TOKEN_2') ?: ($_ENV['BOT_TOKEN_2'] ?? '');

$bot1 = new Bot($token1);
$bots = [$bot1];

if ($token2 !== '') {
  $bots[] = new Bot($token2);
  fwrite(STDOUT, "Starting two bots...\n");
} else {
  fwrite(STDOUT, "BOT_TOKEN_2 not set; running single-bot mode.\n");
}

$dispatcher = new Dispatcher();

// The `$bot` kwarg is always the specific bot that received the update.
// We truncate the token to show just the numeric bot-id portion.
$dispatcher->message->register(static function (Message $event, Bot $bot): void {
  $text = $event->text ?? '';
  // The bot token format is "<bot_id>:<random_part>"; extract the ID prefix.
  $tokenId = explode(':', $bot->token)[0];
  $event->answer("Bot #{$tokenId} received: {$text}")->emit();
});

$dispatcher->startup->register(static function (array $bots): void {
  $count = count($bots);
  fwrite(STDOUT, "Polling started for {$count} bot(s).\n");
});

$dispatcher->shutdown->register(static function (): void {
  fwrite(STDOUT, "Polling stopped.\n");
});

// runPolling accepts variadic bots; each gets its own polling fiber.
$dispatcher->runPolling(new PollingOptions(), ...$bots);
