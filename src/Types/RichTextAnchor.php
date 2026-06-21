<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * An anchor.
 *
 * Source: https://core.telegram.org/bots/api#richtextanchor
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextAnchor extends RichText
{
  public function __construct(
    public readonly string $name,
    public readonly string $type = 'anchor',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
