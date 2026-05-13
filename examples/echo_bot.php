#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Minimal echo bot — port of upstream `aiogram/examples/echo_bot.py`.
 *
 * Run it: `BOT_TOKEN=123:abc php examples/echo_bot.php`. The bot replies to
 * every text message with the same text, demonstrating that
 * `Dispatcher::runPolling` drives the generated `Bot` facade and that
 * `Message::answer` (codegen'd from `aliases.yml`) routes through the bot
 * bound to the deserialized update.
 *
 * Tokens come from `BotFather` (https://t.me/BotFather).
 */
require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

// Echo handler: replies with the same text back. `Message::answer` is the
// codegen'd shortcut that builds a `SendMessage` already bound to the bot
// that originally received the update — calling `->emit()` dispatches it
// through the session middleware chain.
$dispatcher->message->register(static function (Message $event): void {
  $text = $event->text ?? '';

  if ($text === '') {
    return;
  }
  $event->answer($text)->emit();
});

fwrite(STDOUT, "Echo bot starting…\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
