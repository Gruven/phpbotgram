<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a live photo.
 *
 * Source: https://core.telegram.org/bots/api#livephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class LivePhoto extends TelegramObject
{
  /**
   * @param null|list<PhotoSize> $photo
   */
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $width,
    public readonly int $height,
    public readonly int $duration,
    public readonly ?array $photo = null,
    public readonly ?string $mimeType = null,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
