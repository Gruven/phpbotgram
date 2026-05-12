<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a video message (available in Telegram apps as of v.4.0).
 *
 * Source: https://core.telegram.org/bots/api#videonote
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class VideoNote extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $length,
    public readonly int $duration,
    public readonly ?PhotoSize $thumbnail = null,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
