<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene\Attribute;

use Attribute;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\SceneAction;

/**
 * Marks a scene method as a handler for `inline_query` events.
 *
 * Mirrors `on.inline_query` from `OnMarker` (`aiogram/fsm/scene.py:967`).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class OnInlineQuery extends OnAttribute
{
  public function __construct(
    ?SceneAction $action = null,
    ?After $after = null,
    Filter ...$filters,
  ) {
    parent::__construct('inline_query', $action, $after, ...$filters);
  }
}
