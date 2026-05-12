<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an issue with the reverse side of a document. The error is considered resolved when the file with reverse side of the document changes.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerrorreverseside
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorReverseSide extends PassportElementError
{
  public function __construct(
    public readonly string $source,
    public readonly string $type,
    public readonly string $fileHash,
    public readonly string $message,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
