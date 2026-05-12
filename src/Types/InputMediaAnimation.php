<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an animation file (GIF or H.264/MPEG-4 AVC video without sound) to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmediaanimation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaAnimation extends InputMedia
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $type,
    public readonly InputFile|string $media,
    public readonly ?InputFile $thumbnail = null,
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    public readonly ?array $captionEntities = null,
    public readonly ?bool $showCaptionAboveMedia = null,
    public readonly ?int $width = null,
    public readonly ?int $height = null,
    public readonly ?int $duration = null,
    public readonly ?bool $hasSpoiler = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
