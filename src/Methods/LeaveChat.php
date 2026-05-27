<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method for your bot to leave a group, supergroup or channel. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#leavechat
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class LeaveChat extends TelegramMethod
{
  public const string ApiMethod = 'leaveChat';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
