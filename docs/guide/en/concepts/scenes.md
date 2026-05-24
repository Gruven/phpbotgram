# Scenes

A scene is a class-shaped FSM state with attribute-driven event binding. It groups every handler for a single conversation step into one class so the flow reads top-down.

## How it works

### Defining a scene

[`Scene`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene.html) is the abstract base. A subclass declares its state via the [`#[SceneState('name')]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-Attribute-SceneState.html) class attribute and registers handlers with the [`#[OnMessage]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-Attribute-OnMessage.html), [`#[OnCallbackQuery]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-Attribute-OnCallbackQuery.html), [`#[OnChatMember]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-Attribute-OnChatMember.html), etc. method attributes from `Fsm/Scene/Attribute/`. The framework reflects the class once at registration time, builds a [`SceneConfig`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-SceneConfig.html) recording every handler and its target lifecycle stage, and caches the config per class.

The following scene echoes every message back to the user and exits when the user sends `/done` (from `examples/scene.php`):

```php
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnMessage;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use Gruven\PhpBotGram\Types\Message;

#[SceneState('greeting')]
final class GreetingScene extends Scene
{
    #[OnMessage]
    public function onMessage(Message $event): void
    {
        $text = $event->text ?? '';

        if ($text === '/done') {
            $this->wizard->exit();
            $event->answer("Goodbye! You have left the greeting scene.")->emit();
            return;
        }

        $event->answer("(Greeting scene) You said: {$text}\nSend /done to exit.")->emit();
    }
}
```

`$this->wizard` is a [`SceneWizard`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-SceneWizard.html) instance injected by the framework. Its `exit()`, `goto()`, `back()`, and `retake()` methods drive all transitions.

### Registering scenes and entering them

Registration goes through [`SceneRegistry`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-SceneRegistry.html). `$registry->add([MyScene::class, OtherScene::class])` reflects each class, wires its handlers onto the appropriate dispatcher observers behind a `StateFilter` keyed to the scene's `SceneState`, and registers the scene-manager middleware. The `add()` call is explicit, not auto-discovered — aiogram's `SceneRegistry.add(*scenes)` also requires explicit registration, and the port keeps that contract because metaclass-style auto-import is unidiomatic in PHP. The registry composes with the dispatcher's router model: each scene's handler attaches as a regular per-handler registration on the matching observer, just with a `StateFilter` pre-baked so the dispatch only fires when the FSM state matches.

The [`ScenesManager`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-ScenesManager.html) is the per-request handle injected into handlers as the `scenes` kwarg. Handlers call `$scenes->enter(MyScene::class)` to transition from a non-scene handler into a scene, or `$scenes->close()` to abandon any in-flight scene. The manager threads through the dispatcher's middleware chain alongside `FsmContext`, so a handler can declare both `function (Message $event, FsmContext $state, ScenesManager $scenes)` and receive both.

The full wiring for the greeting scene above (from `examples/scene.php`):

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistry;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;
use Gruven\PhpBotGram\Types\Message;

$bot = new Bot(getenv('BOT_TOKEN'));
$dispatcher = new Dispatcher();

$registry = new SceneRegistry($dispatcher);
$registry->add([GreetingScene::class]);

// /start — enter the scene from a plain message handler.
$dispatcher->message->register(
    static function (Message $event, ScenesManager $scenes): void {
        $event->answer("Welcome! Entering the greeting scene.")->emit();
        $scenes->enter(GreetingScene::class);
    },
    filters: [new Command('start')],
);

$dispatcher->runPolling(new PollingOptions(), $bot);
```

### Lifecycle hooks and multi-step wizards

Lifecycle hooks let scenes react to transitions. Override `enter()`, `leave()`, `exit()`, `back()`, or `retake()` on the subclass; the framework calls them at the right transition point. The [`SceneAction`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-SceneAction.html) enum tags `#[On*]` attributes with `Enter` / `Leave` markers so a single method can serve as "the handler that fires *when the user enters this scene*", e.g. `#[OnMessage(action: SceneAction::Enter)]` on a `welcome` method. The default `enter()` / `leave()` / `exit()` / `back()` / `retake()` implementations return `null`; subclasses override only the hooks they need.

The `after:` parameter on `#[OnMessage]` specifies what the framework should do automatically after the handler returns. The [`After`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-After.html) value wraps a `SceneAction` constant and an optional target state. The following two-question quiz wires this up end-to-end (from `examples/quiz_scene.php`):

```php
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\Scene;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnMessage;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use Gruven\PhpBotGram\Fsm\SceneAction;
use Gruven\PhpBotGram\Types\Message;

#[SceneState('quiz:q1')]
final class QuestionOneScene extends Scene
{
    // Ask Q1 when this scene is entered.
    #[OnMessage(action: SceneAction::Enter)]
    public function onEnter(Message $event): void
    {
        $event->answer("Question 1: What is 2 + 2?")->emit();
    }

    // Store the answer then automatically move to Q2.
    // new After(SceneAction::Enter, 'quiz:q2') is equivalent to After::goto('quiz:q2')
    #[OnMessage(after: new After(SceneAction::Enter, 'quiz:q2'))]
    public function onAnswer(Message $event): void
    {
        $this->wizard->updateData(['q1' => $event->text ?? '']);
    }
}

#[SceneState('quiz:q2')]
final class QuestionTwoScene extends Scene
{
    #[OnMessage(action: SceneAction::Enter)]
    public function onEnter(Message $event): void
    {
        $event->answer("Question 2: What is the capital of France?")->emit();
    }

    // Store the answer, show results, then exit the FSM.
    // new After(SceneAction::Exit) is equivalent to After::exit()
    #[OnMessage(after: new After(SceneAction::Exit))]
    public function onAnswer(Message $event): void
    {
        $this->wizard->updateData(['q2' => $event->text ?? '']);
        $data = $this->wizard->getData();
        $q1 = is_string($data['q1'] ?? null) ? $data['q1'] : '(no answer)';
        $q2 = is_string($data['q2'] ?? null) ? $data['q2'] : '(no answer)';
        $event->answer("Quiz complete!\nQ1: {$q1}\nQ2: {$q2}")->emit();
    }
}
```

Register both scenes together and enter the first one from `/start`:

```php
use Gruven\PhpBotGram\Fsm\Scene\SceneRegistry;
use Gruven\PhpBotGram\Fsm\Scene\ScenesManager;

$registry = new SceneRegistry($dispatcher);
$registry->add([QuestionOneScene::class, QuestionTwoScene::class]);

$dispatcher->message->register(
    static function (Message $event, ScenesManager $scenes): void {
        $scenes->enter(QuestionOneScene::class);
    },
    filters: [new Command('start')],
);
```

`SceneWizard` also exposes `goto(NextScene::class)` for imperative transitions, `retake()` to re-enter the current scene, and `back()` to roll back via the history manager. The [`HistoryManager`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-HistoryManager.html) records the previous scene plus a copy of its FSM data on each `goto()`, so `back()` restores both state and payload. The [`HistoryManagerInterface`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-HistoryManagerInterface.html) seam lets you swap in a no-op manager for flat scenes that don't need rollback.

## Trade-offs

Scenes are explicit. The PHP port deliberately rejects aiogram's metaclass `__init_subclass__` magic — every scene must be passed to `SceneRegistry::add`. The trade is more boilerplate vs. predictability: a class that looks like a scene but isn't registered silently does nothing in aiogram, while in phpbotgram it's a clear "you forgot to register me". This was a deliberate choice during the port (see [Architecture decisions](architecture-decisions.md)) and we believe the explicit form is the right default for PHP.

State is stored as a string by the underlying FSM, so two scenes named `WaitingForName` collide if both declare `#[SceneState('WaitingForName')]`. There is no auto-namespacing — the state name you declare is the state name written to storage. Use namespacing manually if you have a large bot (`'shop:WaitingForName'`, etc.) or rely on the convention that the scene class name maps 1:1 to a unique state name. Aiogram has the same collision risk; the port doesn't try to invent a different convention.

The history manager stores a deep-copy of FSM data per push, in memory inside the scene's own storage entry. For deep flows with heavy data this can grow the storage payload. If your bot has long multi-screen flows, audit `HistoryManager::push` overhead — or set `HistoryManager` to a no-op for that scene if you don't need `back()`. The deep-copy semantics are necessary: if `back()` restored a reference to the *same* data dict, in-place mutations during the forward flow would alter the rollback target.

Scene method reflection runs once per subclass at registration time. The cost is small but real if you have many scenes. The `SceneConfig` cache is per-class, keyed by `static::class`, so repeated registrations of the same class (rare in production, common in tests) share the reflection result. Tests that spin up the scene registry many times benefit from this caching.

`SceneAction` has `Enter`, `Leave`, `Exit`, and `Back` cases — there is no `Retake`. `Enter` and `Leave` are the lifecycle points you bind to with the `#[On*](action:)` marker; `Exit` and `Back` are driven imperatively via the wizard (`$wizard->exit()`, `$wizard->back()`). If you need logic on `back()`, override the scene's `back()` method directly.

## See also

- [FSM](fsm.md)
- [API reference: Scene](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene.html)
- [API reference: SceneRegistry](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-SceneRegistry.html)
- [API reference: SceneWizard](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-SceneWizard.html)
- [API reference: ScenesManager](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-ScenesManager.html)
