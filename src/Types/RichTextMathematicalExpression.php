<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A mathematical expression.
 *
 * Source: https://core.telegram.org/bots/api#richtextmathematicalexpression
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextMathematicalExpression extends RichText
{
  public function __construct(
    public readonly string $expression,
    public readonly string $type = 'mathematical_expression',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
