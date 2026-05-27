<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents a change of a reaction on a message performed by a user.
 *
 * Source: https://core.telegram.org/bots/api#messagereactionupdated
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageReactionUpdated extends TelegramObject
{
  /**
   * @param list<ReactionType> $oldReaction
   * @param list<ReactionType> $newReaction
   */
  public function __construct(
    public readonly Chat $chat,
    public readonly int $messageId,
    public readonly DateTime $date,
    public readonly array $oldReaction,
    public readonly array $newReaction,
    public readonly ?User $user = null,
    public readonly ?Chat $actorChat = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
