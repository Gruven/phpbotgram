<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\MessageEntity;

/**
 * Gifts a Telegram Premium subscription to the given user. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#giftpremiumsubscription
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class GiftPremiumSubscription extends TelegramMethod
{
  public const string ApiMethod = 'giftPremiumSubscription';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly int $monthCount,
    public readonly int $starCount,
    public readonly ?string $text = null,
    public readonly ?string $textParseMode = null,
    /** @var list<MessageEntity> */
    public readonly ?array $textEntities = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
