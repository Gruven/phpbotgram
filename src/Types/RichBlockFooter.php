<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A footer, corresponding to the HTML tag <footer>.
 *
 * Source: https://core.telegram.org/bots/api#richblockfooter
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockFooter extends RichBlock
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $type = 'footer',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
