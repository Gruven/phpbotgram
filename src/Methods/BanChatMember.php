<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Use this method to ban a user in a group, a supergroup or a channel. In the case of supergroups and channels, the user will not be able to return to the chat on their own using invite links, etc., unless unbanned first. The bot must be an administrator in the chat for this to work and must have the appropriate administrator rights. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#banchatmember
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class BanChatMember extends TelegramMethod
{
  public const string ApiMethod = 'banChatMember';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $userId,
    public readonly null|DateInterval|DateTime|int $untilDate = null,
    public readonly ?bool $revokeMessages = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
