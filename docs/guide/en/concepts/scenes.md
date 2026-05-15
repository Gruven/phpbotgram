# Scenes

A scene is a class-shaped FSM state with attribute-driven event
binding. It groups every handler for a single conversation step into
one class so the flow reads top-down.

## How it works

[`Scene`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene.html)
is the abstract base. A subclass declares its state via the
[`#[SceneState('name')]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-Attribute-SceneState.html)
class attribute and registers handlers with the
[`#[OnMessage]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-Attribute-OnMessage.html),
[`#[OnCallbackQuery]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-Attribute-OnCallbackQuery.html),
[`#[OnChatMember]`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-Attribute-OnChatMember.html),
etc. method attributes from `Fsm/Scene/Attribute/`. The framework
reflects the class once at registration time, builds a
[`SceneConfig`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-SceneConfig.html)
recording every handler and its target lifecycle stage, and caches
the config per class.

Registration goes through
[`SceneRegistry`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-SceneRegistry.html).
`$registry->add([MyScene::class, OtherScene::class])` reflects each
class, wires its handlers onto the appropriate dispatcher observers
behind a `StateFilter` keyed to the scene's `SceneState`, and
registers the scene-manager middleware. The `add()` call is explicit,
not auto-discovered — aiogram's `SceneRegistry.add(*scenes)` also
requires explicit registration, and the port keeps that contract
because metaclass-style auto-import is unidiomatic in PHP. The
registry composes with the dispatcher's router model: each scene's
handler attaches as a regular per-handler registration on the matching
observer, just with a `StateFilter` pre-baked so the dispatch only
fires when the FSM state matches.

[`SceneWizard`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-SceneWizard.html)
is the imperative API exposed inside scene methods.
`$this->wizard->goto(NextScene::class)` transitions to another scene;
`$this->wizard->retake()` re-enters the current scene;
`$this->wizard->back()` rolls back to the previous one via the
history manager; `$this->wizard->exit()` clears the FSM and runs the
scene's `exit()` lifecycle hook. History tracking is owned by
[`HistoryManager`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-HistoryManager.html);
each push records the previous scene plus a copy of its FSM data, so
`back()` restores both state and payload. The `HistoryManager`
implementation is swappable via the
[`HistoryManagerInterface`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-HistoryManagerInterface.html)
seam — a no-op manager works fine for flat scenes that don't need
rollback.

Lifecycle hooks let scenes react to transitions. Override `enter()`,
`leave()`, `exit()`, `back()`, or `retake()` on the subclass; the
framework calls them at the right transition point. The
[`SceneAction`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-SceneAction.html)
enum tags `#[On*]` attributes with `Enter` / `Leave`
markers so a single method can serve as "the handler that fires
*when the user enters this scene*", e.g.
`#[OnMessage(action: SceneAction::Enter)]` on a `welcome` method.
The default `enter()` / `leave()` / `exit()` / `back()` /
`retake()` implementations return `null`; subclasses override only the
hooks they need.

The
[`ScenesManager`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-ScenesManager.html)
is the per-request handle injected into handlers as the `scenes` kwarg.
Handlers call `$scenes->enter(MyScene::class)` to transition from a
non-scene handler into a scene, or `$scenes->exit()` to abandon any
in-flight scene. The manager threads through the dispatcher's
middleware chain alongside `FsmContext`, so a handler can declare
both `function (Message $event, FsmContext $state, ScenesManager
$scenes)` and receive both.

## Trade-offs

Scenes are explicit. The PHP port deliberately rejects aiogram's
metaclass `__init_subclass__` magic — every scene must be passed to
`SceneRegistry::add`. The trade is more boilerplate vs.
predictability: a class that looks like a scene but isn't registered
silently does nothing in aiogram, while in phpbotgram it's a clear
"you forgot to register me". This was a deliberate choice during the
port (see [Architecture decisions](architecture-decisions.md)) and we
believe the explicit form is the right default for PHP.

State is stored as a string by the underlying FSM, so two scenes
named `WaitingForName` collide if both declare
`#[SceneState('WaitingForName')]`. There is no auto-namespacing —
the state name you declare is the state name written to storage.
Use namespacing manually if you have a large bot
(`'shop:WaitingForName'`, etc.) or rely on the convention that
the scene class name maps 1:1 to a unique state name. Aiogram has
the same collision risk; the port doesn't try to invent a different
convention.

The history manager stores a deep-copy of FSM data per push, in
memory inside the scene's own storage entry. For deep flows with
heavy data this can grow the storage payload. If your bot has long
multi-screen flows, audit `HistoryManager::push` overhead — or set
`HistoryManager` to a no-op for that scene if you don't need `back()`.
The deep-copy semantics are necessary: if `back()` restored a
reference to the *same* data dict, in-place mutations during the
forward flow would alter the rollback target.

Scene method reflection runs once per subclass at registration time.
The cost is small but real if you have many scenes. The
`SceneConfig` cache is per-class, keyed by `static::class`, so
repeated registrations of the same class (rare in production, common
in tests) share the reflection result. Tests that spin up the scene
registry many times benefit from this caching.

`SceneAction` covers `Enter` and `Leave` but not `Exit` /
`Back` / `Retake`. Those transitions don't have an associated
event-binding because they happen *between* events; they're imperative
calls on the wizard. If you need a handler to fire on `back()`,
override the scene's `back()` method directly.

## See also

- [FSM](fsm.md)
- [API reference: Scene](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene.html)
- [API reference: SceneRegistry](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-SceneRegistry.html)
- [API reference: SceneWizard](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-SceneWizard.html)
- [API reference: ScenesManager](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-Scene-ScenesManager.html)
