<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an issue with the front side of a document. The error is considered resolved when the file with the front side of the document changes.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerrorfrontside
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorFrontSide extends PassportElementError
{
  public function __construct(
    public readonly string $type,
    public readonly string $fileHash,
    public readonly string $message,
    public readonly string $source = 'front_side',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
