<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Rich formatted message.
 *
 * Source: https://core.telegram.org/bots/api#richmessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichMessage extends TelegramObject
{
  /**
   * @param list<RichBlock> $blocks
   */
  public function __construct(
    public readonly array $blocks,
    public readonly ?bool $isRtl = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
