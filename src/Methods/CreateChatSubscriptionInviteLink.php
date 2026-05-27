<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatInviteLink;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Use this method to create a subscription invite link for a channel chat. The bot must have the can_invite_users administrator rights. The link can be edited using the method editChatSubscriptionInviteLink or revoked using the method revokeChatInviteLink. Returns the new invite link as a ChatInviteLink object.
 *
 * Source: https://core.telegram.org/bots/api#createchatsubscriptioninvitelink
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<ChatInviteLink>
 */
final class CreateChatSubscriptionInviteLink extends TelegramMethod
{
  public const string ApiMethod = 'createChatSubscriptionInviteLink';
  public const string ReturnsType = ChatInviteLink::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly DateInterval|DateTime|int $subscriptionPeriod,
    public readonly int $subscriptionPrice,
    public readonly ?string $name = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
