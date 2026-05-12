<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an issue in an unspecified place. The error is considered resolved when new data is added.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerrorunspecified
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportElementErrorUnspecified extends PassportElementError
{
  public function __construct(
    public readonly string $source,
    public readonly string $type,
    public readonly string $elementHash,
    public readonly string $message,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
