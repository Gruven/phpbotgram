<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an issue with a list of scans. The error is considered resolved when the list of files containing the scans changes.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerrorfiles
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorFiles extends PassportElementError
{
  /**
   * @param list<string> $fileHashes
   */
  public function __construct(
    public readonly string $type,
    public readonly array $fileHashes,
    public readonly string $message,
    public readonly string $source = 'files',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
