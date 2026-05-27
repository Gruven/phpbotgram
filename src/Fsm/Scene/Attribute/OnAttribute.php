<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene\Attribute;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\SceneAction;

/**
 * Abstract base for all `#[On*]` scene event-marker attributes.
 *
 * Each concrete subclass (e.g. `OnMessage`, `OnCallbackQuery`) corresponds to
 * one Telegram event observer and injects the observer name constant at
 * construction time.
 *
 * **Design note — Option B (14 attributes with `?SceneAction $action`):**
 * Upstream uses 14 `ObserverMarker` instances on a single `OnMarker` namespace
 * class, with per-marker `.enter()`, `.leave()`, `.exit()`, `.back()` chainable
 * factories (`aiogram/fsm/scene.py:975-980`).  The plan mentions "25 total"
 * attributes, but the cleanest PHP equivalent is 14 attribute classes that
 * carry the lifecycle action as an optional constructor arg:
 *
 *   // run on any message while in scene
 *   #[OnMessage]
 *
 *   // run when entering the scene via a message
 *   #[OnMessage(action: SceneAction::Enter)]
 *
 *   // run on callback_query and then go back
 *   #[OnCallbackQuery(after: After::back())]
 *
 * This yields 14 classes instead of 25 or 70, while covering all the same
 * behavioural combinations. Task 5.9+ will read these attributes via
 * reflection to wire scene handlers into the dispatcher.
 *
 * Attributes flags:
 *   - `Attribute::TARGET_METHOD` — valid on methods only.
 *   - `Attribute::IS_REPEATABLE` — a method may carry multiple `#[On*]`
 *     decorators (e.g., the same handler fires on both `Enter` and a default
 *     message event).
 */
abstract readonly class OnAttribute
{
  /**
   * The Telegram observer name this attribute targets (e.g. `'message'`,
   * `'callback_query'`). Set by subclass constructors.
   */
  public string $event;

  /**
   * Optional lifecycle action filter.
   *
   * - `null` — the handler runs for any event of this type while in scene.
   * - `SceneAction::Enter` — runs when entering the scene via this event.
   * - `SceneAction::Leave` — runs when leaving the scene via this event.
   * - `SceneAction::Exit`  — runs when exiting FSM via this event.
   * - `SceneAction::Back`  — runs when rolling back via this event.
   */
  public ?SceneAction $action;

  /**
   * Optional post-handler action.
   *
   * When non-null, the framework will execute this action (exit / back /
   * goto) automatically after the handler method returns, saving the user
   * from having to call `$this->wizard->exit()` etc. manually.
   *
   * Mirrors the `after=` parameter on `ObserverDecorator`
   * (`aiogram/fsm/scene.py:113`).
   */
  public ?After $after;

  /**
   * Optional dispatcher filters that must pass before this handler fires.
   *
   * Mirrors the `*filters` positional args on `ObserverMarker.__call__`
   * (`aiogram/fsm/scene.py:939-944`).
   *
   * @var list<Filter>
   */
  public array $filters;

  /**
   * @param string $event Observer name injected by each concrete subclass.
   * @param null|SceneAction $action Lifecycle action filter.
   * @param null|After $after Post-handler action.
   * @param Filter ...$filters Additional dispatcher filters.
   */
  public function __construct(
    string $event,
    ?SceneAction $action = null,
    ?After $after = null,
    Filter ...$filters,
  ) {
    $this->event = $event;
    $this->action = $action;
    $this->after = $after;
    $this->filters = array_values($filters);
  }
}
