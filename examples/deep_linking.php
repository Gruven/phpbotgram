#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Deep-link /start payloads.
 *
 * What this demonstrates:
 *   - Creating a t.me link with DeepLinking::createStartLink().
 *   - Matching only /start commands that contain a payload.
 *   - Reading the parsed payload from CommandObject.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/deep_linking.php
 *
 * Then send /link to receive a shareable link, or open the generated link in
 * Telegram to trigger /start with a payload.
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\CommandObject;
use Gruven\PhpBotGram\Filters\CommandStart;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\DeepLinking;

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

$dispatcher->message->register(
  static function (Message $event, Bot $bot): void {
    $payload = 'ref_' . ($event->fromUser?->id ?? $event->chat->id);
    $link = DeepLinking::createStartLink($bot, payload: $payload);

    $event->answer("Share this link:\n{$link}")->emit();
  },
  filters: [new Command('link')],
);

$dispatcher->message->register(
  static function (Message $event, CommandObject $command): void {
    $payload = $command->args ?? '';

    $event->answer("Welcome from deep link. Payload: {$payload}")->emit();
  },
  filters: [new CommandStart(deepLink: true)],
);

$dispatcher->message->register(
  static function (Message $event): void {
    $event->answer('Send /link to create a deep link for this bot.')->emit();
  },
  filters: [new CommandStart(deepLink: false)],
);

fwrite(STDOUT, "Deep-linking bot starting...\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
