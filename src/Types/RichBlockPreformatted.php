<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A preformatted text block, corresponding to the nested HTML tags <pre> and <code>.
 *
 * Source: https://core.telegram.org/bots/api#richblockpreformatted
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockPreformatted extends RichBlock
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $type = 'pre',
    public readonly ?string $language = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
