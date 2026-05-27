<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types\Shortcuts;

use Gruven\PhpBotGram\Types\BusinessConnection;
use Gruven\PhpBotGram\Types\BusinessMessagesDeleted;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\ChatBoostRemoved;
use Gruven\PhpBotGram\Types\ChatBoostUpdated;
use Gruven\PhpBotGram\Types\ChatJoinRequest;
use Gruven\PhpBotGram\Types\ChatMemberUpdated;
use Gruven\PhpBotGram\Types\ChosenInlineResult;
use Gruven\PhpBotGram\Types\InlineQuery;
use Gruven\PhpBotGram\Types\ManagedBotUpdated;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageReactionCountUpdated;
use Gruven\PhpBotGram\Types\MessageReactionUpdated;
use Gruven\PhpBotGram\Types\PaidMediaPurchased;
use Gruven\PhpBotGram\Types\Poll;
use Gruven\PhpBotGram\Types\PollAnswer;
use Gruven\PhpBotGram\Types\PreCheckoutQuery;
use Gruven\PhpBotGram\Types\ShippingQuery;

/**
 * Hand-authored shortcut helpers for `Update`.
 *
 * Loaded by `HandAuthoredShortcutsIntegrator` (Phase 2 codegen stage 8)
 * and stitched into the regenerated `Update` class via a
 * `use UpdateShortcuts;` directive.
 *
 * Mirrors aiogram's `Update.event_type` accessor — the dispatcher uses
 * it to route a single update to the right handler chain without
 * walking every optional slot manually.
 *
 * The match arms below mirror the schema's optional-field declaration
 * order so the first non-null field's wire name wins. In a real
 * Telegram payload exactly one of these fields is populated, so the
 * ordering is purely defensive against tests / synthetic payloads.
 *
 * @property null|Message $message promoted property on the using class
 * @property null|Message $editedMessage promoted property on the using class
 * @property null|Message $channelPost promoted property on the using class
 * @property null|Message $editedChannelPost promoted property on the using class
 * @property null|BusinessConnection $businessConnection promoted property on the using class
 * @property null|Message $businessMessage promoted property on the using class
 * @property null|Message $editedBusinessMessage promoted property on the using class
 * @property null|BusinessMessagesDeleted $deletedBusinessMessages promoted property on the using class
 * @property null|Message $guestMessage promoted property on the using class
 * @property null|MessageReactionUpdated $messageReaction promoted property on the using class
 * @property null|MessageReactionCountUpdated $messageReactionCount promoted property on the using class
 * @property null|InlineQuery $inlineQuery promoted property on the using class
 * @property null|ChosenInlineResult $chosenInlineResult promoted property on the using class
 * @property null|CallbackQuery $callbackQuery promoted property on the using class
 * @property null|ShippingQuery $shippingQuery promoted property on the using class
 * @property null|PreCheckoutQuery $preCheckoutQuery promoted property on the using class
 * @property null|PaidMediaPurchased $purchasedPaidMedia promoted property on the using class
 * @property null|Poll $poll promoted property on the using class
 * @property null|PollAnswer $pollAnswer promoted property on the using class
 * @property null|ChatMemberUpdated $myChatMember promoted property on the using class
 * @property null|ChatMemberUpdated $chatMember promoted property on the using class
 * @property null|ChatJoinRequest $chatJoinRequest promoted property on the using class
 * @property null|ChatBoostUpdated $chatBoost promoted property on the using class
 * @property null|ChatBoostRemoved $removedChatBoost promoted property on the using class
 * @property null|ManagedBotUpdated $managedBot promoted property on the using class
 */
trait UpdateShortcuts
{
  public function eventType(): ?string
  {
    if ($this->message !== null) {
      return 'message';
    }

    if ($this->editedMessage !== null) {
      return 'edited_message';
    }

    if ($this->channelPost !== null) {
      return 'channel_post';
    }

    if ($this->editedChannelPost !== null) {
      return 'edited_channel_post';
    }

    if ($this->businessConnection !== null) {
      return 'business_connection';
    }

    if ($this->businessMessage !== null) {
      return 'business_message';
    }

    if ($this->editedBusinessMessage !== null) {
      return 'edited_business_message';
    }

    if ($this->deletedBusinessMessages !== null) {
      return 'deleted_business_messages';
    }

    if ($this->guestMessage !== null) {
      return 'guest_message';
    }

    if ($this->messageReaction !== null) {
      return 'message_reaction';
    }

    if ($this->messageReactionCount !== null) {
      return 'message_reaction_count';
    }

    if ($this->inlineQuery !== null) {
      return 'inline_query';
    }

    if ($this->chosenInlineResult !== null) {
      return 'chosen_inline_result';
    }

    if ($this->callbackQuery !== null) {
      return 'callback_query';
    }

    if ($this->shippingQuery !== null) {
      return 'shipping_query';
    }

    if ($this->preCheckoutQuery !== null) {
      return 'pre_checkout_query';
    }

    if ($this->purchasedPaidMedia !== null) {
      return 'purchased_paid_media';
    }

    if ($this->poll !== null) {
      return 'poll';
    }

    if ($this->pollAnswer !== null) {
      return 'poll_answer';
    }

    if ($this->myChatMember !== null) {
      return 'my_chat_member';
    }

    if ($this->chatMember !== null) {
      return 'chat_member';
    }

    if ($this->chatJoinRequest !== null) {
      return 'chat_join_request';
    }

    if ($this->chatBoost !== null) {
      return 'chat_boost';
    }

    if ($this->removedChatBoost !== null) {
      return 'removed_chat_boost';
    }

    if ($this->managedBot !== null) {
      return 'managed_bot';
    }

    return null;
  }
}
