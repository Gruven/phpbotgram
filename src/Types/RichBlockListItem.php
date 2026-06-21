<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * An item of a list.
 *
 * Source: https://core.telegram.org/bots/api#richblocklistitem
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockListItem extends TelegramObject
{
  /**
   * @param list<RichBlock> $blocks
   */
  public function __construct(
    public readonly string $label,
    public readonly array $blocks,
    public readonly ?bool $hasCheckbox = null,
    public readonly ?bool $isChecked = null,
    public readonly ?int $value = null,
    public readonly ?string $type = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
