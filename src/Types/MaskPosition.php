<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the position on faces where a mask should be placed by default.
 *
 * Source: https://core.telegram.org/bots/api#maskposition
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MaskPosition extends TelegramObject
{
  public function __construct(
    public readonly string $point,
    public readonly float $xShift,
    public readonly float $yShift,
    public readonly float $scale,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
