<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an issue with the selfie with a document. The error is considered resolved when the file with the selfie changes.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerrorselfie
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorSelfie extends PassportElementError
{
  public function __construct(
    public readonly string $type,
    public readonly string $fileHash,
    public readonly string $message,
    public readonly string $source = 'selfie',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
