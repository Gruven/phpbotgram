<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Represents a video to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmediavideo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaVideo extends InputMedia
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly InputFile|string $media,
    public readonly string $type = 'video',
    public readonly ?InputFile $thumbnail = null,
    public readonly null|InputFile|string $cover = null,
    public readonly null|DateInterval|DateTime|int $startTimestamp = null,
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    public readonly ?array $captionEntities = null,
    public readonly ?bool $showCaptionAboveMedia = null,
    public readonly ?int $width = null,
    public readonly ?int $height = null,
    public readonly ?int $duration = null,
    public readonly ?bool $supportsStreaming = null,
    public readonly ?bool $hasSpoiler = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
