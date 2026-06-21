#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Quiz scene — port of upstream `aiogram/examples/quiz_scene.py`.
 *
 * What this demonstrates:
 *   - Multi-state scene navigation: After::goto() transitions between scene steps.
 *   - After::exit() clears FSM state after the final answer.
 *   - SceneWizard::getData() / updateData() accumulates per-session answers.
 *   - Multiple Scene subclasses registered into one SceneRegistry.
 *
 * Note on PHP attribute syntax: PHP attributes accept only constant expressions
 * as arguments. `After::exit()` / `After::goto()` are static factory calls,
 * which are NOT valid attribute argument expressions. Use the `new After(...)`
 * constructor directly with the appropriate `SceneAction` constant:
 *
 *   After::exit()       ≡  new After(SceneAction::Exit)
 *   After::back()       ≡  new After(SceneAction::Back)
 *   After::goto(state)  ≡  new After(SceneAction::Enter, 'state')
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/quiz_scene.php
 *
 * Test flow:
 *   1. /start → question 1
 *   2. Answer anything → question 2
 *   3. Answer anything → results summary, FSM cleared
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\Logic\InvertFilter;
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnMessage;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistry;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;
use Gruven\PhpBotGram\Fsm\SceneAction;
use Gruven\PhpBotGram\Types\Message;

/**
 * First quiz question scene.
 *
 * After handling a message the framework automatically transitions to the
 * second question via `new After(SceneAction::Enter, 'quiz:q2')`.
 */
#[SceneState('quiz:q1')]
final class QuestionOneScene extends Scene
{
  /**
   * Ask the first question when entering this scene. Mirrors upstream
   * `@on.message.enter()` — the lifecycle handler is a separately-named
   * method tagged with `#[OnMessage(action: SceneAction::Enter)]`, not
   * an override of the base `Scene::enter()` method (which only sets
   * FSM state and dispatches the lifecycle event).
   */
  #[OnMessage(action: SceneAction::Enter)]
  public function onEnter(Message $event): void
  {
    $event->answer('Question 1: What is 2 + 2?')->emit();
  }

  #[OnMessage(filters: new Command('cancel'))]
  public function onCancel(Message $event): void
  {
    $this->wizard->exit();
    $event->answer('Quiz cancelled.')->emit();
  }

  /**
   * Store the answer then move to question 2.
   * `new After(SceneAction::Enter, 'quiz:q2')` ≡ `After::goto('quiz:q2')`.
   */
  #[OnMessage(after: new After(SceneAction::Enter, 'quiz:q2'), filters: new InvertFilter(new Command('cancel')))]
  public function onAnswer(Message $event): void
  {
    $this->wizard->updateData(['q1' => $event->text ?? '']);
  }
}

/**
 * Second quiz question scene.
 *
 * After handling a message the framework exits the FSM via
 * `new After(SceneAction::Exit)`.
 */
#[SceneState('quiz:q2')]
final class QuestionTwoScene extends Scene
{
  /**
   * Ask the second question when entering this scene. See QuestionOneScene
   * for the lifecycle-attribute pattern.
   */
  #[OnMessage(action: SceneAction::Enter)]
  public function onEnter(Message $event): void
  {
    $event->answer('Question 2: What is the capital of France?')->emit();
  }

  #[OnMessage(filters: new Command('cancel'))]
  public function onCancel(Message $event): void
  {
    $this->wizard->exit();
    $event->answer('Quiz cancelled.')->emit();
  }

  /**
   * Store the answer, show results, then exit the FSM.
   * `new After(SceneAction::Exit)` ≡ `After::exit()`.
   */
  #[OnMessage(after: new After(SceneAction::Exit), filters: new InvertFilter(new Command('cancel')))]
  public function onAnswer(Message $event): void
  {
    $this->wizard->updateData(['q2' => $event->text ?? '']);
    $data = $this->wizard->getData();
    $q1 = is_string($data['q1'] ?? null) ? $data['q1'] : '(no answer)';
    $q2 = is_string($data['q2'] ?? null) ? $data['q2'] : '(no answer)';
    $event->answer(
      "Quiz complete!\n"
        . "Q1 answer: {$q1}\n"
        . "Q2 answer: {$q2}",
    )->emit();
  }
}

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

$registry = new SceneRegistry($dispatcher);
$registry->add([QuestionOneScene::class, QuestionTwoScene::class]);

// /start — enter the first question scene.
$dispatcher->message->register(
  static function (Message $event, ScenesManager $scenes): void {
    $scenes->enter(QuestionOneScene::class);
  },
  filters: [new Command('start')],
);

// /cancel outside scenes. Scene-level cancel handlers above handle active scenes.
$dispatcher->message->register(
  static function (Message $event, ScenesManager $scenes): void {
    $scenes->close();
    $event->answer('Quiz cancelled.')->emit();
  },
  filters: [new Command('cancel')],
);

// Default handler outside the scene.
$dispatcher->message->register(static function (Message $event): void {
  $event->answer('Send /start to begin the quiz.')->emit();
});

fwrite(STDOUT, "Quiz scene bot starting...\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
