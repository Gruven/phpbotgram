<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents an audio file to be treated as music by the Telegram clients.
 *
 * Source: https://core.telegram.org/bots/api#audio
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Audio extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $duration,
    public readonly ?string $performer = null,
    public readonly ?string $title = null,
    public readonly ?string $fileName = null,
    public readonly ?string $mimeType = null,
    public readonly ?int $fileSize = null,
    public readonly ?PhotoSize $thumbnail = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
