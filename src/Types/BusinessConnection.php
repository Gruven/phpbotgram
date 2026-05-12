<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Describes the connection of the bot with a business account.
 *
 * Source: https://core.telegram.org/bots/api#businessconnection
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BusinessConnection extends TelegramObject
{
  public function __construct(
    public readonly string $id,
    public readonly User $user,
    public readonly int $userChatId,
    public readonly DateTime $date,
    public readonly ?BusinessBotRights $rights,
    public readonly bool $isEnabled,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
