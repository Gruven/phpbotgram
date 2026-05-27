<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\MessageEntity;

/**
 * Sends a gift to the given user or channel chat. The gift can't be converted to Telegram Stars by the receiver. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#sendgift
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SendGift extends TelegramMethod
{
  public const string ApiMethod = 'sendGift';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $giftId,
    public readonly ?int $userId = null,
    public readonly int|string|null $chatId = null,
    public readonly ?bool $payForUpgrade = null,
    public readonly ?string $text = null,
    public readonly ?string $textParseMode = null,
    /** @var list<MessageEntity> */
    public readonly ?array $textEntities = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
