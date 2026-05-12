<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to change the description of a group, a supergroup or a channel. The bot must be an administrator in the chat for this to work and must have the appropriate administrator rights. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setchatdescription
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetChatDescription extends TelegramMethod
{
  public const string ApiMethod = 'setChatDescription';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly ?string $description = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
