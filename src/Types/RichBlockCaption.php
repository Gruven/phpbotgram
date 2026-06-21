<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Caption of a rich formatted block.
 *
 * Source: https://core.telegram.org/bots/api#richblockcaption
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockCaption extends TelegramObject
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   * @param null|list<array<array-key,mixed>|RichText|string>|RichText|string $credit
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly array|RichText|string|null $credit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
