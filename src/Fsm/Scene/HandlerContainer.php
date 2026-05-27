<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Fsm\After;

/**
 * Bundles a named handler callable with its associated filters and
 * optional post-handler action.
 *
 * Mirrors `HandlerContainer` (`aiogram/fsm/scene.py:179-185`):
 *
 * ```python
 *
 * @dataclass(slots=True)
 * class HandlerContainer:
 *     name: str
 *     handler: CallbackType
 *     filters: tuple[CallbackType, ...]
 *     after: After | None = None
 * ```
 *
 * Used by `SceneConfig::$handlers` to describe all the registered scene
 * method handlers resolved from `#[On*]` attributes.
 */
final readonly class HandlerContainer
{
  /**
   * @param string $name Human-readable name (typically the method name).
   * @param callable $handler The callable that handles the event.
   * @param list<Filter> $filters Filters that must pass before the
   *                              handler fires.
   * @param ?After $after Optional post-handler action (exit / back / goto).
   */
  public function __construct(
    public string $name,
    /** @var callable */
    public mixed $handler,
    /** @var list<Filter> */
    public array $filters = [],
    public ?After $after = null,
  ) {}
}
