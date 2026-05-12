<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents an animation file (GIF or H.264/MPEG-4 AVC video without sound).
 *
 * Source: https://core.telegram.org/bots/api#animation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Animation extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $width,
    public readonly int $height,
    public readonly int $duration,
    public readonly ?PhotoSize $thumbnail = null,
    public readonly ?string $fileName = null,
    public readonly ?string $mimeType = null,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
