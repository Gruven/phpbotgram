#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Finite-state machine (FSM) bot — port of upstream `aiogram/examples/finite_state_machine.py`.
 *
 * What this demonstrates:
 *   - StatesGroup + State declarations.
 *   - MemoryStorage (the default, auto-wired by Dispatcher).
 *   - StateFilter to gate handlers to specific FSM states.
 *   - FsmContext injected by FsmContextMiddleware to set/clear state.
 *   - A simple multi-step form: asks name, then age, then confirms.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/finite_state_machine.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\StateFilter;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\StatesGroup;
use Gruven\PhpBotGram\Types\Message;

// Define the states group.
class Form extends StatesGroup
{
  public static State $name;
  public static State $age;
}
Form::bootstrap();

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
// Dispatcher auto-wires MemoryStorage when no storage is passed.
$dispatcher = new Dispatcher();

// /start — enter the form.
$dispatcher->message->register(
  static function (Message $event, FsmContext $state): void {
    $state->setState(Form::$name);
    $event->answer("Hello! What's your name?")->emit();
  },
  filters: [new Command('start')],
);

// Collect name — only fires when state === Form:name.
$dispatcher->message->register(
  static function (Message $event, FsmContext $state): void {
    $name = $event->text ?? '';
    $state->updateData(['name' => $name]);
    $state->setState(Form::$age);
    $event->answer("Nice to meet you, {$name}! How old are you?")->emit();
  },
  filters: [new StateFilter(Form::$name)],
);

// Collect age — only fires when state === Form:age.
$dispatcher->message->register(
  static function (Message $event, FsmContext $state): void {
    $text = $event->text ?? '';

    if (!ctype_digit($text)) {
      $event->answer('Please enter a valid number for your age.')->emit();

      return;
    }
    $data = $state->getData();
    $name = is_string($data['name'] ?? null) ? $data['name'] : 'stranger';
    $state->setState(null);  // clear the state
    $event->answer("Great! So your name is {$name} and you are {$text} years old.")->emit();
  },
  filters: [new StateFilter(Form::$age)],
);

// /cancel — reset the form.
$dispatcher->message->register(
  static function (Message $event, FsmContext $state): void {
    $state->setState(null);
    $event->answer('Form cancelled.')->emit();
  },
  filters: [new Command('cancel'), new StateFilter('*')],
);

fwrite(STDOUT, "FSM bot starting...\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
