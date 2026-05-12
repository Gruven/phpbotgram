<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an issue with one of the files that constitute the translation of a document. The error is considered resolved when the file changes.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerrortranslationfile
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorTranslationFile extends PassportElementError
{
  public function __construct(
    public readonly string $type,
    public readonly string $fileHash,
    public readonly string $message,
    public readonly string $source = 'translation_file',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
