<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Converts a given regular gift to Telegram Stars. Requires the can_convert_gifts_to_stars business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#convertgifttostars
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class ConvertGiftToStars extends TelegramMethod
{
  public const string ApiMethod = 'convertGiftToStars';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly string $ownedGiftId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
