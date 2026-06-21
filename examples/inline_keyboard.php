#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Inline keyboard and typed callback data.
 *
 * What this demonstrates:
 *   - Building InlineKeyboardMarkup with InlineKeyboardBuilder.
 *   - Packing callback payloads with CallbackData.
 *   - Filtering callback_query updates back into a typed payload object.
 *   - Auto-answering callback queries with CallbackAnswerMiddleware.
 *   - Editing the original bot message after a button press.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/inline_keyboard.php
 *
 * Then send /palette and press a button.
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\CallbackData;
use Gruven\PhpBotGram\Filters\CallbackPrefix;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswer;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswerMiddleware;
use Gruven\PhpBotGram\Utils\Keyboard\InlineKeyboardBuilder;

#[CallbackPrefix('pal')]
final class PaletteCallback extends CallbackData
{
  public function __construct(
    public readonly string $color,
  ) {}
}

function paletteKeyboard(?string $selected = null): InlineKeyboardMarkup
{
  $builder = new InlineKeyboardBuilder();

  foreach (['red' => 'Red', 'green' => 'Green', 'blue' => 'Blue'] as $color => $label) {
    $builder->button(
      text: ($selected === $color ? '* ' : '') . $label,
      callbackData: new PaletteCallback($color),
    );
  }

  return $builder->adjust(3)->asMarkup();
}

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

// Install once: the middleware answers every accepted callback query after the
// handler returns. Handlers may still set the answer text before that happens.
$dispatcher->callbackQuery->innerMiddleware(new CallbackAnswerMiddleware());

$dispatcher->message->register(
  static function (Message $event): void {
    $event->answer(
      text: 'Pick a color:',
      replyMarkup: paletteKeyboard(),
    )->emit();
  },
  filters: [new Command('palette')],
);

$dispatcher->callbackQuery->register(
  static function (
    CallbackQuery $event,
    PaletteCallback $callback_data,
    CallbackAnswer $callback_answer,
  ): void {
    $selectedText = "Selected color: {$callback_data->color}";
    $callback_answer->text = "Selected {$callback_data->color}";

    if (!$event->message instanceof Message) {
      return;
    }

    if ($event->message->text === $selectedText) {
      $callback_answer->text = "{$callback_data->color} is already selected";

      return;
    }

    $event->message->editText(
      text: $selectedText,
      replyMarkup: paletteKeyboard($callback_data->color),
    )->emit();
  },
  filters: [PaletteCallback::filter()],
);

fwrite(STDOUT, "Inline-keyboard bot starting...\n");
$dispatcher->runPolling(new PollingOptions(
  allowedUpdates: ['message', 'callback_query'],
), $bot);
