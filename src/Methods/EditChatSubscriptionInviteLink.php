<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatInviteLink;

/**
 * Use this method to edit a subscription invite link created by the bot. The bot must have the can_invite_users administrator rights. Returns the edited invite link as a ChatInviteLink object.
 *
 * Source: https://core.telegram.org/bots/api#editchatsubscriptioninvitelink
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<ChatInviteLink>
 */
final class EditChatSubscriptionInviteLink extends TelegramMethod
{
  public const string ApiMethod = 'editChatSubscriptionInviteLink';
  public const string ReturnsType = ChatInviteLink::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $inviteLink,
    public readonly ?string $name = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
