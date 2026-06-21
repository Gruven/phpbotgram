<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about an option added to a poll.
 *
 * Source: https://core.telegram.org/bots/api#polloptionadded
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PollOptionAdded extends TelegramObject
{
  /**
   * @param null|list<MessageEntity> $optionTextEntities
   */
  public function __construct(
    public readonly string $optionPersistentId,
    public readonly string $optionText,
    public readonly ?MaybeInaccessibleMessage $pollMessage = null,
    public readonly ?array $optionTextEntities = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
