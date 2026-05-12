<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a general file to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmediadocument
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaDocument extends InputMedia
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly InputFile|string $media,
    public readonly string $type = 'document',
    public readonly ?InputFile $thumbnail = null,
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    public readonly ?array $captionEntities = null,
    public readonly ?bool $disableContentTypeDetection = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
