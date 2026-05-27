<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Shortcuts\UpdateShortcuts;

/**
 * This object represents an incoming update.
 * At most one of the optional fields can be present in any given update.
 *
 * Source: https://core.telegram.org/bots/api#update
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Update extends TelegramObject
{
  use UpdateShortcuts;

  public function __construct(
    public readonly int $updateId,
    public readonly ?Message $message = null,
    public readonly ?Message $editedMessage = null,
    public readonly ?Message $channelPost = null,
    public readonly ?Message $editedChannelPost = null,
    public readonly ?BusinessConnection $businessConnection = null,
    public readonly ?Message $businessMessage = null,
    public readonly ?Message $editedBusinessMessage = null,
    public readonly ?BusinessMessagesDeleted $deletedBusinessMessages = null,
    public readonly ?Message $guestMessage = null,
    public readonly ?MessageReactionUpdated $messageReaction = null,
    public readonly ?MessageReactionCountUpdated $messageReactionCount = null,
    public readonly ?InlineQuery $inlineQuery = null,
    public readonly ?ChosenInlineResult $chosenInlineResult = null,
    public readonly ?CallbackQuery $callbackQuery = null,
    public readonly ?ShippingQuery $shippingQuery = null,
    public readonly ?PreCheckoutQuery $preCheckoutQuery = null,
    public readonly ?PaidMediaPurchased $purchasedPaidMedia = null,
    public readonly ?Poll $poll = null,
    public readonly ?PollAnswer $pollAnswer = null,
    public readonly ?ChatMemberUpdated $myChatMember = null,
    public readonly ?ChatMemberUpdated $chatMember = null,
    public readonly ?ChatJoinRequest $chatJoinRequest = null,
    public readonly ?ChatBoostUpdated $chatBoost = null,
    public readonly ?ChatBoostRemoved $removedChatBoost = null,
    public readonly ?ManagedBotUpdated $managedBot = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
