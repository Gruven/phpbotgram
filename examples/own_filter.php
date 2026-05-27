#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Custom filter — port of upstream `aiogram/examples/own_filter.py`.
 *
 * What this demonstrates:
 *   - Extending the abstract `Filter` class to create a custom predicate.
 *   - Returning `array<string, mixed>` from a filter to inject kwargs into the
 *     handler (the "context addition" pattern).
 *   - Registering the custom filter on a handler via the `filters:` argument.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/own_filter.php
 *
 * Test:
 *   Send "hello" → the bot greets you as a friend.
 *   Send anything else → the default handler replies.
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Message;

/**
 * A filter that accepts messages containing the word "hello" (case-insensitive).
 *
 * When it matches it also injects a `greeting` key into the kwargs bag so the
 * downstream handler can use it directly as a named parameter.
 */
final class ContainsHelloFilter extends Filter
{
  public function __invoke(object $event, mixed ...$kwargs): array|bool
  {
    if (!$event instanceof Message) {
      return false;
    }

    $text = strtolower($event->text ?? '');

    if (!str_contains($text, 'hello')) {
      return false;
    }

    // Return array to inject extra kwargs into the handler.
    return ['greeting' => 'Hello, friend!'];
  }
}

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

// This handler fires only when ContainsHelloFilter accepts the message.
// The `$greeting` parameter is injected by the filter's array return.
$dispatcher->message->register(
  static function (Message $event, string $greeting): void {
    $event->answer($greeting)->emit();
  },
  filters: [new ContainsHelloFilter()],
);

// Default catch-all.
$dispatcher->message->register(static function (Message $event): void {
  $event->answer('Try saying hello!')->emit();
});

fwrite(STDOUT, "Own-filter bot starting...\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
