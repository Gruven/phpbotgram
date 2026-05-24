<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatInviteLink;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Use this method to create an additional invite link for a chat. The bot must be an administrator in the chat for this to work and must have the appropriate administrator rights. The link can be revoked using the method revokeChatInviteLink. Returns the new invite link as ChatInviteLink object.
 *
 * Source: https://core.telegram.org/bots/api#createchatinvitelink
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<ChatInviteLink>
 */
final class CreateChatInviteLink extends TelegramMethod
{
  public const string ApiMethod = 'createChatInviteLink';
  public const string ReturnsType = ChatInviteLink::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly ?string $name = null,
    public readonly DateInterval|DateTime|int|null $expireDate = null,
    public readonly ?int $memberLimit = null,
    public readonly ?bool $createsJoinRequest = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
