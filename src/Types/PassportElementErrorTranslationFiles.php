<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an issue with the translated version of a document. The error is considered resolved when a file with the document translation change.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerrortranslationfiles
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorTranslationFiles extends PassportElementError
{
  /**
   * @param list<string> $fileHashes
   */
  public function __construct(
    public readonly string $source,
    public readonly string $type,
    public readonly array $fileHashes,
    public readonly string $message,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
