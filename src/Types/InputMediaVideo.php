<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Represents a video to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmediavideo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaVideo extends InputMedia implements InputPollMediaInterface, InputPollOptionMediaInterface
{
  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly InputFile|string $media,
    public readonly string $type = 'video',
    public readonly ?InputFile $thumbnail = null,
    public readonly InputFile|string|null $cover = null,
    public readonly DateInterval|DateTime|int|null $startTimestamp = null,
    public readonly ?string $caption = null,
    public readonly BotDefault|string|null $parseMode = new BotDefault('parse_mode'),
    public readonly ?array $captionEntities = null,
    public readonly bool|BotDefault|null $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
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
