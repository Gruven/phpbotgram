<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene\Attribute;

use Attribute;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\SceneAction;

/**
 * Marks a scene method as a handler for `edited_channel_post` events.
 *
 * Mirrors `on.edited_channel_post` from `OnMarker` (`aiogram/fsm/scene.py:978`).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class OnEditedChannelPost extends OnAttribute
{
  public function __construct(
    ?SceneAction $action = null,
    ?After $after = null,
    Filter ...$filters,
  ) {
    parent::__construct('edited_channel_post', $action, $after, ...$filters);
  }
}
