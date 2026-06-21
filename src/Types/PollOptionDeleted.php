<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about an option deleted from a poll.
 *
 * Source: https://core.telegram.org/bots/api#polloptiondeleted
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PollOptionDeleted extends TelegramObject
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
