<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a voice note.
 *
 * Source: https://core.telegram.org/bots/api#voice
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Voice extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $duration,
    public readonly ?string $mimeType = null,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
