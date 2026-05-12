<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents one size of a photo or a file / sticker thumbnail.
 *
 * Source: https://core.telegram.org/bots/api#photosize
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PhotoSize extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $width,
    public readonly int $height,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
