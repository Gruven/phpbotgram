<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to decline a suggested post in a direct messages chat. The bot must have the 'can_manage_direct_messages' administrator right in the corresponding channel chat. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#declinesuggestedpost
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeclineSuggestedPost extends TelegramMethod
{
  public const string ApiMethod = 'declineSuggestedPost';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $chatId,
    public readonly int $messageId,
    public readonly ?string $comment = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
