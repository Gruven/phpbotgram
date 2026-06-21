<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents a video file.
 *
 * Source: https://core.telegram.org/bots/api#video
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Video extends TelegramObject
{
  /**
   * @param null|list<PhotoSize> $cover
   * @param null|list<VideoQuality> $qualities
   */
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $width,
    public readonly int $height,
    public readonly int $duration,
    public readonly ?PhotoSize $thumbnail = null,
    public readonly ?array $cover = null,
    public readonly ?DateTime $startTimestamp = null,
    public readonly ?array $qualities = null,
    public readonly ?string $fileName = null,
    public readonly ?string $mimeType = null,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
