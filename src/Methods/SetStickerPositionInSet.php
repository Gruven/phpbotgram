<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to move a sticker in a set created by the bot to a specific position. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setstickerpositioninset
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetStickerPositionInSet extends TelegramMethod
{
  public const string ApiMethod = 'setStickerPositionInSet';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $sticker,
    public readonly int $position,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
