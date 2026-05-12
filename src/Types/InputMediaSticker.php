<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a sticker file to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmediasticker
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaSticker extends InputPollOptionMedia
{
  public function __construct(
    public readonly string $type,
    public readonly string $media,
    public readonly ?string $emoji = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
