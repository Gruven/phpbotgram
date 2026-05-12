<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a forum topic.
 *
 * Source: https://core.telegram.org/bots/api#forumtopic
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ForumTopic extends TelegramObject
{
  public function __construct(
    public readonly int $messageThreadId,
    public readonly string $name,
    public readonly int $iconColor,
    public readonly ?string $iconCustomEmojiId = null,
    public readonly ?bool $isNameImplicit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
