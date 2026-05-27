<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about new members invited to a video chat.
 *
 * Source: https://core.telegram.org/bots/api#videochatparticipantsinvited
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class VideoChatParticipantsInvited extends TelegramObject
{
  /**
   * @param list<User> $users
   */
  public function __construct(
    public readonly array $users,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
