<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputFile;

/**
 * Use this method to upload a file with a sticker for later use in the createNewStickerSet, addStickerToSet, or replaceStickerInSet methods (the file can be used multiple times). Returns the uploaded File on success.
 *
 * Source: https://core.telegram.org/bots/api#uploadstickerfile
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class UploadStickerFile extends TelegramMethod
{
  public const string ApiMethod = 'uploadStickerFile';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly InputFile $sticker,
    public readonly string $stickerFormat,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
