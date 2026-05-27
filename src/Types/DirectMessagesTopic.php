<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a topic of a direct messages chat.
 *
 * Source: https://core.telegram.org/bots/api#directmessagestopic
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class DirectMessagesTopic extends TelegramObject
{
  public function __construct(
    public readonly int $topicId,
    public readonly ?User $user = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
