# Build a wizard with scenes

## When to use this

Multi-step flows — onboarding, quizzes, checkout — that need to
remember where the user is and what they answered. Scenes encapsulate
each step into a class and let you transition with one method call.

## Solution

```php
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnMessage;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistry;
use Gruven\PhpBotGram\Fsm\SceneAction;
use Gruven\PhpBotGram\Types\Message;

#[SceneState('quiz:q1')]
final class QuestionOneScene extends Scene
{
    #[OnMessage(action: SceneAction::Enter)]
    public function onEnter(Message $event): void
    {
        $event->answer('Question 1: What is 2 + 2?')->emit();
    }

    #[OnMessage(after: new After(SceneAction::Enter, 'quiz:q2'))]
    public function onAnswer(Message $event): void
    {
        $this->wizard->updateData(['q1' => $event->text ?? '']);
    }
}

$registry = new SceneRegistry($dispatcher);
$registry->add([QuestionOneScene::class, QuestionTwoScene::class]);
```

Each scene subclasses
[`Scene`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene.html)
and declares its state with `#[SceneState]`. `#[OnMessage]` binds a
method to message updates while the scene is active; the optional
`after:` argument transitions to the next state when the handler
returns. The wizard exposes `updateData`, `getData`, and `exit`
imperatively.

## Pitfalls

- `After::goto()` is NOT a valid attribute argument — PHP attributes
  accept only constant expressions. Use `new After(SceneAction::Enter,
  'state')` instead.
- Scenes must be passed to
  [`SceneRegistry::add()`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-SceneRegistry.html)
  explicitly — there is no auto-discovery. A class with `#[SceneState]`
  that nobody registered will silently never fire.
- State names are global strings — `WaitingForName` in two scenes
  collides. Namespace manually (`'shop:WaitingForName'`) for large bots.
  See [Scenes](../concepts/scenes.md) for the lifecycle model.
