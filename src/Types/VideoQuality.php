<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a video file of a specific quality.
 *
 * Source: https://core.telegram.org/bots/api#videoquality
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class VideoQuality extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $width,
    public readonly int $height,
    public readonly string $codec,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
