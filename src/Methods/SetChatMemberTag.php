<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to set a tag for a regular member in a group or a supergroup. The bot must be an administrator in the chat for this to work and must have the can_manage_tags administrator right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setchatmembertag
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetChatMemberTag extends TelegramMethod
{
  public const string ApiMethod = 'setChatMemberTag';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $userId,
    public readonly ?string $tag = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
