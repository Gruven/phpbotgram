<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with a mathematical expression in LaTeX format, corresponding to the custom HTML tag <tg-math-block>.
 *
 * Source: https://core.telegram.org/bots/api#richblockmathematicalexpression
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockMathematicalExpression extends RichBlock
{
  public function __construct(
    public readonly string $expression,
    public readonly string $type = 'mathematical_expression',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
