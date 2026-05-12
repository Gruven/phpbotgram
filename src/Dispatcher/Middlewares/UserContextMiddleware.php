<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Middlewares;

use Closure;
use Gruven\PhpBotGram\Types\BusinessConnection;
use Gruven\PhpBotGram\Types\BusinessMessagesDeleted;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\ChatBoostRemoved;
use Gruven\PhpBotGram\Types\ChatBoostSourcePremium;
use Gruven\PhpBotGram\Types\ChatBoostUpdated;
use Gruven\PhpBotGram\Types\ChatJoinRequest;
use Gruven\PhpBotGram\Types\ChatMemberUpdated;
use Gruven\PhpBotGram\Types\ChosenInlineResult;
use Gruven\PhpBotGram\Types\InaccessibleMessage;
use Gruven\PhpBotGram\Types\InlineQuery;
use Gruven\PhpBotGram\Types\ManagedBotUpdated;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageReactionCountUpdated;
use Gruven\PhpBotGram\Types\MessageReactionUpdated;
use Gruven\PhpBotGram\Types\PaidMediaPurchased;
use Gruven\PhpBotGram\Types\PollAnswer;
use Gruven\PhpBotGram\Types\PreCheckoutQuery;
use Gruven\PhpBotGram\Types\ShippingQuery;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Update;

/**
 * Dispatcher-side middleware that resolves the user/chat/thread context for
 * the incoming event and writes the canonical kwargs into `$data` so that
 * downstream handlers, filters, and inner middlewares can bind them via
 * reflection.
 *
 * Mirrors aiogram's `aiogram.dispatcher.middlewares.user_context.UserContextMiddleware`.
 *
 * Differences from upstream:
 *
 * 1. Upstream raises `RuntimeError` when invoked with anything other than an
 *    `Update`. The port accepts either an `Update` (the dispatcher-wired
 *    case) or an already-unwrapped child event — `resolveContext()` dispatches
 *    on the runtime type, so callers that already hold the child can reuse
 *    the resolver without rewrapping.
 * 2. Upstream conditionally writes `event_from_user` / `event_chat` /
 *    `event_thread_id` only when they're non-null. The port writes all four
 *    keys unconditionally (set to `null` when absent) so handlers can rely on
 *    `array_key_exists` semantics. Inner middlewares that compute fallbacks
 *    (e.g. an FSM context that synthesises a chat) can still overwrite the
 *    key — null is just the default.
 */
final class UserContextMiddleware extends BaseMiddleware
{
  public const string EVENT_CONTEXT_KEY = 'event_context';
  public const string EVENT_FROM_USER_KEY = 'event_from_user';
  public const string EVENT_CHAT_KEY = 'event_chat';
  public const string EVENT_THREAD_ID_KEY = 'event_thread_id';

  public function __invoke(Closure $handler, object $event, array $data): mixed
  {
    $context = self::resolveContext($event);

    $data[self::EVENT_CONTEXT_KEY] = $context;
    $data[self::EVENT_FROM_USER_KEY] = $context->user;
    $data[self::EVENT_CHAT_KEY] = $context->chat;
    $data[self::EVENT_THREAD_ID_KEY] = $context->threadId;

    return $handler($event, $data);
  }

  /**
   * Resolve the `EventContext` for an arbitrary Telegram event.
   *
   * The dispatcher passes an `Update` here, but the resolver is also useful
   * for tests and for synthetic invocations that hold the already-unwrapped
   * child event. When given an `Update`, the first non-null child slot wins
   * (matching `UpdateShortcuts::eventType()`'s declaration-order tie-break).
   *
   * Non-`TelegramObject` payloads (e.g. `ErrorEvent`) carry no user/chat
   * context themselves; the function returns an empty `EventContext` for
   * those so the keys still exist in `$data` with documented null defaults.
   *
   * Returns an empty `EventContext` for events that don't carry any
   * recognised context (e.g. `Poll`, unrecognised types, or a bare `Update`
   * with every slot null).
   */
  public static function resolveContext(object $event): EventContext
  {
    if ($event instanceof Update) {
      $child = self::unwrapUpdate($event);

      if ($child === null) {
        return new EventContext();
      }

      return self::resolveContext($child);
    }

    if ($event instanceof Message) {
      return new EventContext(
        chat: $event->chat,
        user: $event->fromUser,
        threadId: $event->isTopicMessage === true ? $event->messageThreadId : null,
        businessConnectionId: $event->businessConnectionId,
      );
    }

    if ($event instanceof CallbackQuery) {
      $message = $event->message;

      if ($message === null) {
        return new EventContext(user: $event->fromUser);
      }

      // InaccessibleMessage exposes only `chat`; the thread / business-connection
      // fields live on the concrete Message subtype. Upstream guards both with
      // `not isinstance(message, InaccessibleMessage)`.
      if ($message instanceof InaccessibleMessage) {
        return new EventContext(chat: $message->chat, user: $event->fromUser);
      }

      if ($message instanceof Message) {
        return new EventContext(
          chat: $message->chat,
          user: $event->fromUser,
          threadId: $message->isTopicMessage === true ? $message->messageThreadId : null,
          businessConnectionId: $message->businessConnectionId,
        );
      }

      return new EventContext(user: $event->fromUser);
    }

    if ($event instanceof InlineQuery) {
      return new EventContext(user: $event->fromUser);
    }

    if ($event instanceof ChosenInlineResult) {
      return new EventContext(user: $event->fromUser);
    }

    if ($event instanceof ShippingQuery) {
      return new EventContext(user: $event->fromUser);
    }

    if ($event instanceof PreCheckoutQuery) {
      return new EventContext(user: $event->fromUser);
    }

    if ($event instanceof PollAnswer) {
      return new EventContext(chat: $event->voterChat, user: $event->user);
    }

    if ($event instanceof ChatMemberUpdated) {
      return new EventContext(chat: $event->chat, user: $event->fromUser);
    }

    if ($event instanceof ChatJoinRequest) {
      return new EventContext(chat: $event->chat, user: $event->fromUser);
    }

    if ($event instanceof MessageReactionUpdated) {
      return new EventContext(chat: $event->chat, user: $event->user);
    }

    if ($event instanceof MessageReactionCountUpdated) {
      return new EventContext(chat: $event->chat);
    }

    if ($event instanceof ChatBoostUpdated) {
      // Only the premium source carries a "sender" user — other sources have
      // a recipient user that we deliberately ignore (upstream parity).
      $source = $event->boost->source;

      if ($source instanceof ChatBoostSourcePremium) {
        return new EventContext(chat: $event->chat, user: $source->user);
      }

      return new EventContext(chat: $event->chat);
    }

    if ($event instanceof ChatBoostRemoved) {
      return new EventContext(chat: $event->chat);
    }

    if ($event instanceof BusinessConnection) {
      // No `chat` here — the schema exposes only `user_chat_id: int`, not a
      // full Chat object.
      return new EventContext(
        user: $event->user,
        businessConnectionId: $event->id,
      );
    }

    if ($event instanceof BusinessMessagesDeleted) {
      return new EventContext(
        chat: $event->chat,
        businessConnectionId: $event->businessConnectionId,
      );
    }

    if ($event instanceof PaidMediaPurchased) {
      return new EventContext(user: $event->fromUser);
    }

    if ($event instanceof ManagedBotUpdated) {
      return new EventContext(user: $event->user);
    }

    return new EventContext();
  }

  /**
   * Return whichever child event slot is populated on the `Update`, or
   * `null` if every optional field is empty. Mirrors the declaration-order
   * priority of `UpdateShortcuts::eventType()`.
   */
  private static function unwrapUpdate(Update $update): ?TelegramObject
  {
    return $update->message
      ?? $update->editedMessage
      ?? $update->channelPost
      ?? $update->editedChannelPost
      ?? $update->businessConnection
      ?? $update->businessMessage
      ?? $update->editedBusinessMessage
      ?? $update->deletedBusinessMessages
      ?? $update->guestMessage
      ?? $update->messageReaction
      ?? $update->messageReactionCount
      ?? $update->inlineQuery
      ?? $update->chosenInlineResult
      ?? $update->callbackQuery
      ?? $update->shippingQuery
      ?? $update->preCheckoutQuery
      ?? $update->purchasedPaidMedia
      ?? $update->poll
      ?? $update->pollAnswer
      ?? $update->myChatMember
      ?? $update->chatMember
      ?? $update->chatJoinRequest
      ?? $update->chatBoost
      ?? $update->removedChatBoost
      ?? $update->managedBot;
  }
}
