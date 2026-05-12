<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a photo to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmediaphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaPhoto extends InputMedia
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly InputFile|string $media,
    public readonly string $type = 'photo',
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    public readonly ?array $captionEntities = null,
    public readonly ?bool $showCaptionAboveMedia = null,
    public readonly ?bool $hasSpoiler = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
