<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents an answer of a user in a non-anonymous poll.
 *
 * Source: https://core.telegram.org/bots/api#pollanswer
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PollAnswer extends TelegramObject
{
  /**
   * @param list<int> $optionIds
   * @param list<string> $optionPersistentIds
   */
  public function __construct(
    public readonly string $pollId,
    public readonly ?Chat $voterChat,
    public readonly ?User $user,
    public readonly array $optionIds,
    public readonly array $optionPersistentIds,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
