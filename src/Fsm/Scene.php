<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

use Gruven\PhpBotGram\Fsm\Scene\Attribute\SceneState;
use ReflectionClass;

/**
 * Abstract base for all scene classes in the Scene subsystem.
 *
 * A scene encapsulates a logical conversation step — the user is "in" a
 * scene while the FSM state matches the scene's declared state.  Subclasses
 * declare their FSM state via the `#[SceneState]` class attribute and attach
 * handlers via the `#[On*]` method attributes (`OnMessage`, `OnCallbackQuery`,
 * etc. in `Scene/Attribute/`).
 *
 * **Task 5.8 shell:** This class carries the abstract lifecycle method stubs
 * and the `sceneState()` reflection helper. Full wiring of `$wizard`,
 * `add_to_router`, `as_handler` etc. lands in Tasks 5.9–5.12.
 *
 * Mirrors `Scene` (`aiogram/fsm/scene.py:297-441`).
 *
 * ## Usage
 *
 *   #[SceneState('greeting')]
 *   final class GreetingScene extends Scene
 *   {
 *       #[OnMessage]
 *       public function onMessage(object $message): void
 *       {
 *           // ...
 *       }
 *
 *       #[OnMessage(action: SceneAction::Enter)]
 *       public function onEnter(object $message): void
 *       {
 *           // ...
 *       }
 *   }
 *
 * ## Lifecycle stubs
 *
 * - `enter()` — invoked by the framework when the scene is entered.
 * - `leave()` — invoked when the scene is left via `SceneWizard::leave()`.
 * - `exit()`  — invoked when FSM is cleared via `SceneWizard::exit()`.
 * - `back()`  — invoked when rolling back via `SceneWizard::back()`.
 * - `retake()` — invoked when the scene is re-entered (e.g. loop iteration).
 *
 * Default implementations return `null`. Subclasses override as needed.
 * Returning `null` from a lifecycle hook is safe — the framework checks the
 * return type dynamically.
 *
 * ## SceneWizard
 *
 * The `$wizard` property will be typed as `SceneWizard` once Task 5.9 lands.
 * For Task 5.8 it is typed as `object` to avoid a forward-reference compile
 * error. Task 5.9 will replace the `object` type hint with
 * `Gruven\PhpBotGram\Fsm\SceneWizard`.
 *
 * @todo Task 5.9: Replace `object $wizard` with `SceneWizard $wizard`.
 */
abstract class Scene
{
  /**
   * The wizard that manages scene transitions for this instance.
   *
   * @todo Task 5.9: tighten to `SceneWizard` once that class exists.
   */
  public object $wizard;

  /**
   * Construct a scene instance.
   *
   * @param object $wizard Scene wizard (Task 5.9 will tighten the type).
   *
   * @todo Task 5.9: Replace `object` with `SceneWizard`.
   */
  public function __construct(object $wizard)
  {
    $this->wizard = $wizard;
  }

  // ------------------------------------------------------------------ //
  // Lifecycle stubs — subclasses override as needed
  // ------------------------------------------------------------------ //

  /**
   * Invoked by the framework when this scene is entered.
   *
   * Mirrors `Scene.enter()` (`aiogram/fsm/scene.py:400-404`).
   * Default: returns `null`. Subclasses may return `mixed`.
   */
  public function enter(mixed ...$kwargs): mixed
  {
    return null;
  }

  /**
   * Invoked when the scene is left via `SceneWizard::leave()`.
   *
   * Mirrors `Scene.leave()` (`aiogram/fsm/scene.py:406-408`).
   * Default: returns `null`.
   */
  public function leave(mixed ...$kwargs): mixed
  {
    return null;
  }

  /**
   * Invoked when FSM is cleared via `SceneWizard::exit()`.
   *
   * Mirrors `Scene.exit()` (`aiogram/fsm/scene.py:410-412`).
   * Default: returns `null`.
   */
  public function exit(mixed ...$kwargs): mixed
  {
    return null;
  }

  /**
   * Invoked when rolling back via `SceneWizard::back()`.
   *
   * Mirrors `Scene.back()` (`aiogram/fsm/scene.py:414-416`).
   * Default: returns `null`.
   */
  public function back(mixed ...$kwargs): mixed
  {
    return null;
  }

  /**
   * Invoked when the scene is re-entered (e.g., after a looping step).
   *
   * No direct upstream equivalent — added for the PHP port's "retake"
   * concept used in wizard-style multi-step scenes.
   * Default: returns `null`.
   */
  public function retake(mixed ...$kwargs): mixed
  {
    return null;
  }

  // ------------------------------------------------------------------ //
  // Reflection helpers
  // ------------------------------------------------------------------ //

  /**
   * Return the FSM state string declared by the `#[SceneState]` attribute on
   * this class (or a subclass), or derive a default from the class short name.
   *
   * Resolution order:
   * 1. The `#[SceneState('explicit_state')]` attribute value (non-null).
   * 2. If the attribute is present but `$state` is `null`, fall back to the
   *    lowercase short class name (mirrors upstream's default where the state
   *    name equals the class name in snake_case).
   * 3. If the attribute is absent entirely, also fall back to the lowercase
   *    short class name.
   *
   * Mirrors the state-name resolution in `Scene.__init_subclass__`
   * (`aiogram/fsm/scene.py:318-322`).
   *
   * @return null|string The resolved FSM state string, or `null` if the
   *                     class has no short name (anonymous classes).
   */
  public static function sceneState(): ?string
  {
    $ref = new ReflectionClass(static::class);

    $attrs = $ref->getAttributes(SceneState::class);

    if ($attrs !== []) {
      /** @var SceneState $inst */
      $inst = $attrs[0]->newInstance();

      if ($inst->state !== null) {
        return $inst->state;
      }
    }

    // Default: lowercase short class name.
    $shortName = $ref->getShortName();

    return $shortName !== '' ? strtolower($shortName) : null;
  }
}
