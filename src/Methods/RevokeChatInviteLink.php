<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatInviteLink;

/**
 * Use this method to revoke an invite link created by the bot. If the primary link is revoked, a new link is automatically generated. The bot must be an administrator in the chat for this to work and must have the appropriate administrator rights. Returns the revoked invite link as ChatInviteLink object.
 *
 * Source: https://core.telegram.org/bots/api#revokechatinvitelink
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<ChatInviteLink>
 */
final class RevokeChatInviteLink extends TelegramMethod
{
  public const string ApiMethod = 'revokeChatInviteLink';
  public const string ReturnsType = ChatInviteLink::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $inviteLink,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
