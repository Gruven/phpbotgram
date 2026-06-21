<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a live photo to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmedialivephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaLivePhoto extends InputMedia implements InputPollMediaInterface, InputPollOptionMediaInterface
{
  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $media,
    public readonly string $photo,
    public readonly string $type = 'live_photo',
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
