<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * The paid media to send is a video.
 *
 * Source: https://core.telegram.org/bots/api#inputpaidmediavideo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputPaidMediaVideo extends InputPaidMedia
{
  public function __construct(
    public readonly string $type,
    public readonly InputFile|string $media,
    public readonly ?InputFile $thumbnail = null,
    public readonly null|InputFile|string $cover = null,
    public readonly null|DateInterval|DateTime|int $startTimestamp = null,
    public readonly ?int $width = null,
    public readonly ?int $height = null,
    public readonly ?int $duration = null,
    public readonly ?bool $supportsStreaming = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
