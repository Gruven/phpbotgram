<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an issue with a document scan. The error is considered resolved when the file with the document scan changes.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerrorfile
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorFile extends PassportElementError
{
  public function __construct(
    public readonly string $type,
    public readonly string $fileHash,
    public readonly string $message,
    public readonly string $source = 'file',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
