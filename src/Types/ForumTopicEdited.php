<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about an edited forum topic.
 *
 * Source: https://core.telegram.org/bots/api#forumtopicedited
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ForumTopicEdited extends TelegramObject
{
  public function __construct(
    public readonly ?string $name = null,
    public readonly ?string $iconCustomEmojiId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
