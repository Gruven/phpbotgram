<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an audio file to be treated as music to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmediaaudio
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaAudio extends InputMedia
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly InputFile|string $media,
    public readonly string $type = 'audio',
    public readonly ?InputFile $thumbnail = null,
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    public readonly ?array $captionEntities = null,
    public readonly ?int $duration = null,
    public readonly ?string $performer = null,
    public readonly ?string $title = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
