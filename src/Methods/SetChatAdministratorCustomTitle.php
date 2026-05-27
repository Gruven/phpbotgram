<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to set a custom title for an administrator in a supergroup promoted by the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setchatadministratorcustomtitle
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetChatAdministratorCustomTitle extends TelegramMethod
{
  public const string ApiMethod = 'setChatAdministratorCustomTitle';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $userId,
    public readonly string $customTitle,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
