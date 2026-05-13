<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene\Attribute;

use Attribute;

/**
 * Class-level attribute that declares the FSM state name for a `Scene`
 * subclass.
 *
 * Mirrors the upstream pattern of passing `state=` as a keyword argument
 * to the `Scene` base in `__init_subclass__` (`aiogram/fsm/scene.py:318-322`).
 * PHP has no `__init_subclass__` equivalent, so the state is declared via a
 * class-level attribute that `Scene::sceneState()` resolves via reflection.
 *
 * When `$state` is `null` (or the attribute is omitted entirely), callers
 * **should** derive the state name from the class's short name (lowercase).
 * That defaulting logic lives in `Scene::sceneState()` rather than here so
 * this attribute stays a pure data carrier.
 *
 *   #[SceneState('greeting')]
 *   final class GreetingScene extends Scene { ... }
 *
 *   // Implicit state defaults to lowercase short class name → 'welcome'
 *   #[SceneState]
 *   final class WelcomeScene extends Scene { ... }
 *
 * Constraints:
 *   - Targets classes only (`Attribute::TARGET_CLASS`).
 *   - Not repeatable (each scene declares exactly one state).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SceneState
{
  /**
   * @param null|string $state Explicit FSM state string. Pass `null` (or
   *                           omit the argument) to signal that the `Scene` base class should
   *                           derive the state from the subclass's short class name.
   */
  public function __construct(
    public ?string $state = null,
  ) {}
}
