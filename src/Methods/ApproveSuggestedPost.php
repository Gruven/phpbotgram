<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Use this method to approve a suggested post in a direct messages chat. The bot must have the 'can_post_messages' administrator right in the corresponding channel chat. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#approvesuggestedpost
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class ApproveSuggestedPost extends TelegramMethod
{
  public const string ApiMethod = 'approveSuggestedPost';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $chatId,
    public readonly int $messageId,
    public readonly DateInterval|DateTime|int|null $sendDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
