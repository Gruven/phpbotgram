<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene\Attribute;

use Attribute;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\SceneAction;

/**
 * Marks a scene method as a handler for `message` events.
 *
 * Mirrors `on.message` / `on.message.enter()` etc. from `OnMarker`
 * (`aiogram/fsm/scene.py:975`). The optional `$action` selects a specific
 * lifecycle phase; when omitted the handler fires for every message while
 * the scene is active.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class OnMessage extends OnAttribute
{
  public function __construct(
    ?SceneAction $action = null,
    ?After $after = null,
    Filter ...$filters,
  ) {
    parent::__construct('message', $action, $after, ...$filters);
  }
}
