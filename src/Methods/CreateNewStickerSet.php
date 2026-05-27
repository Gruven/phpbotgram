<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputSticker;

/**
 * Use this method to create a new sticker set owned by a user. The bot will be able to edit the sticker set thus created. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#createnewstickerset
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class CreateNewStickerSet extends TelegramMethod
{
  public const string ApiMethod = 'createNewStickerSet';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly string $name,
    public readonly string $title,
    /** @var list<InputSticker> */
    public readonly array $stickers,
    public readonly ?string $stickerType = null,
    public readonly ?bool $needsRepainting = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
