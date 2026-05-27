<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to delete a group sticker set from a supergroup. The bot must be an administrator in the chat for this to work and must have the appropriate administrator rights. Use the field can_set_sticker_set optionally returned in getChat requests to check if the bot can use this method. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletechatstickerset
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteChatStickerSet extends TelegramMethod
{
  public const string ApiMethod = 'deleteChatStickerSet';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
