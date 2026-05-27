<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the access settings of a bot.
 *
 * Source: https://core.telegram.org/bots/api#botaccesssettings
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotAccessSettings extends TelegramObject
{
  /**
   * @param list<User> $addedUsers
   */
  public function __construct(
    public readonly bool $isAccessRestricted,
    public readonly ?array $addedUsers = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
