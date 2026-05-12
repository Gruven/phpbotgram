<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about the users whose identifiers were shared with the bot using a KeyboardButtonRequestUsers button.
 *
 * Source: https://core.telegram.org/bots/api#usersshared
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UsersShared extends TelegramObject
{
  /**
   * @param list<SharedUser> $users
   */
  public function __construct(
    public readonly int $requestId,
    public readonly array $users,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
