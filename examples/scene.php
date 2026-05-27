#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Basic Scene — port of upstream `aiogram/examples/scene.py`.
 *
 * What this demonstrates:
 *   - Defining a Scene subclass with #[SceneState] and #[OnMessage].
 *   - SceneRegistry to register scenes with a Dispatcher.
 *   - SceneWizard::enter() / exit() transitions from inside a scene handler.
 *   - ScenesManager injected as `$scenes` kwarg by the framework middleware.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/scene.php
 *
 * Test flow:
 *   1. Send /start → bot enters the "greeting" scene and prompts you.
 *   2. Send any text → scene echoes it back.
 *   3. Send /done → scene exits, bot acknowledges.
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnMessage;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistry;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;
use Gruven\PhpBotGram\Types\Message;

/**
 * A simple "greeting" scene.
 *
 * Any message while in the scene echoes back; /done exits the scene.
 */
#[SceneState('greeting')]
final class GreetingScene extends Scene
{
  /**
   * Fire on every message while the scene is active.
   */
  #[OnMessage]
  public function onMessage(Message $event): void
  {
    $text = $event->text ?? '';

    if ($text === '/done') {
      $this->wizard->exit();
      $event->answer('Goodbye! You have left the greeting scene.')->emit();

      return;
    }

    $event->answer("(Greeting scene) You said: {$text}\nSend /done to exit.")->emit();
  }
}

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

// SceneRegistry wires the per-update ScenesManager middleware and includes
// each scene's sub-router into the dispatcher automatically.
$registry = new SceneRegistry($dispatcher);
$registry->add([GreetingScene::class]);

// /start — enter the scene. The framework injects $scenes (ScenesManager)
// as a handler kwarg because SceneRegistry registered its outer middleware.
$dispatcher->message->register(
  static function (Message $event, ScenesManager $scenes): void {
    $event->answer('Welcome! Entering the greeting scene.')->emit();
    // ScenesManager::enter() sets FSM state and fires the Enter lifecycle.
    $scenes->enter(GreetingScene::class);
  },
  filters: [new Command('start')],
);

// Catch-all outside the scene.
$dispatcher->message->register(static function (Message $event): void {
  $event->answer('Send /start to begin.')->emit();
});

fwrite(STDOUT, "Scene bot starting...\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
