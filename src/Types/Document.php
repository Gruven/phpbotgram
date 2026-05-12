<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a general file (as opposed to photos, voice messages and audio files).
 *
 * Source: https://core.telegram.org/bots/api#document
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Document extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly ?PhotoSize $thumbnail = null,
    public readonly ?string $fileName = null,
    public readonly ?string $mimeType = null,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
