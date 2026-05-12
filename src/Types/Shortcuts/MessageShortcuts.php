<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types\Shortcuts;

use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\ReplyParameters;

/**
 * Hand-authored shortcut helpers for `Message`.
 *
 * Loaded by `HandAuthoredShortcutsIntegrator` (Phase 2 codegen stage 8) and
 * stitched into the regenerated `Message` class via a `use MessageShortcuts;`
 * directive. Methods declared here cover behaviour that cannot be expressed
 * through `aliases.yml`'s lowering grammar — namely the `as_reply_parameters()`
 * helper that the schema's `reply_*` aliases invoke via `self.as_reply_parameters()`
 * in their `fill:` blocks.
 *
 * The integrator collision-checks every declared method against the
 * `aliases.yml`-derived shortcut set for `Message`; clashing names fail
 * codegen rather than silently shadow the generated implementation.
 *
 * @property int $messageId promoted property on the using class
 * @property Chat $chat promoted property on the using class
 */
trait MessageShortcuts
{
  /**
   * Build a `ReplyParameters` referencing this message.
   *
   * Mirrors aiogram's `Message.as_reply_parameters(...)` (full upstream
   * signature): produces a `ReplyParameters` payload pinned to
   * `(message_id, chat_id)` plus the optional quote-formatting controls
   * that aiogram exposes — `allow_sending_without_reply`, `quote`,
   * `quote_parse_mode`, `quote_entities`, `quote_position` — so the
   * generated `reply_*` shortcuts can default `reply_parameters` to
   * "reply to this message" without the caller spelling out the IDs.
   *
   * Both `allowSendingWithoutReply` and `quoteParseMode` default to a
   * `BotDefault(...)` sentinel that mirrors aiogram's `Default(...)`:
   * when the message is bound to a Bot, the bot's `DefaultBotProperties`
   * is consulted for the matching field; when there is no bound bot,
   * the sentinel resolves to null and the server-side default applies.
   * Both sentinels are resolved inside this helper because the
   * `ReplyParameters` property types (`?string`, `?bool`) forbid holding
   * the sentinel through to wire-encode time.
   *
   * @param null|list<MessageEntity> $quoteEntities
   */
  public function asReplyParameters(
    null|bool|BotDefault $allowSendingWithoutReply = new BotDefault('allow_sending_without_reply'),
    ?string $quote = null,
    null|BotDefault|string $quoteParseMode = new BotDefault('parse_mode'),
    ?array $quoteEntities = null,
    ?int $quotePosition = null,
  ): ReplyParameters {
    $resolvedQuoteParseMode = $quoteParseMode;

    if ($resolvedQuoteParseMode instanceof BotDefault) {
      // The sentinel resolves against the bot's DefaultBotProperties when
      // the message is bound to a Bot; otherwise it falls back to null so
      // the server-side default applies. `?string` on ReplyParameters
      // forbids holding the sentinel through to wire-encode time.
      $resolvedQuoteParseMode = $this->bot?->getDefaultProperties()->get($resolvedQuoteParseMode->name);

      if (!is_string($resolvedQuoteParseMode)) {
        $resolvedQuoteParseMode = null;
      }
    }

    $resolvedAllowSendingWithoutReply = $allowSendingWithoutReply;

    if ($resolvedAllowSendingWithoutReply instanceof BotDefault) {
      // Same resolution rule as `quoteParseMode`: bot-bound messages
      // consult `DefaultBotProperties::allowSendingWithoutReply`,
      // un-bound messages fall through to null (server-side default).
      $resolvedAllowSendingWithoutReply = $this->bot?->getDefaultProperties()->get($resolvedAllowSendingWithoutReply->name);

      if (!is_bool($resolvedAllowSendingWithoutReply)) {
        $resolvedAllowSendingWithoutReply = null;
      }
    }

    return new ReplyParameters(
      messageId: $this->messageId,
      chatId: $this->chat->id,
      allowSendingWithoutReply: $resolvedAllowSendingWithoutReply,
      quote: $quote,
      quoteParseMode: $resolvedQuoteParseMode,
      quoteEntities: $quoteEntities,
      quotePosition: $quotePosition,
    );
  }
}
