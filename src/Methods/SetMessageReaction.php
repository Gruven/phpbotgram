<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ReactionType;

/**
 * Use this method to change the chosen reactions on a message. Service messages of some types can't be reacted to. Automatically forwarded messages from a channel to its discussion group have the same available reactions as messages in the channel. Bots can't use paid reactions. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setmessagereaction
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetMessageReaction extends TelegramMethod
{
  public const string ApiMethod = 'setMessageReaction';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $messageId,
    /** @var list<ReactionType> */
    public readonly ?array $reaction = null,
    public readonly ?bool $isBig = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
