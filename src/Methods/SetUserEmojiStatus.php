<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Changes the emoji status for a given user that previously allowed the bot to manage their emoji status via the Mini App method requestEmojiStatusAccess. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setuseremojistatus
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetUserEmojiStatus extends TelegramMethod
{
  public const string ApiMethod = 'setUserEmojiStatus';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly ?string $emojiStatusCustomEmojiId = null,
    public readonly DateInterval|DateTime|int|null $emojiStatusExpirationDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
