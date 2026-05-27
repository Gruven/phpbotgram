<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about the creation, token update, or owner update of a bot that is managed by the current bot.
 *
 * Source: https://core.telegram.org/bots/api#managedbotupdated
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ManagedBotUpdated extends TelegramObject
{
  /** @var array<string, string> */
  public const array WireNames = [
    'botUser' => 'bot',
  ];

  public function __construct(
    public readonly User $user,
    public readonly User $botUser,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
