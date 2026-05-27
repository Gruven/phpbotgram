<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the colors of the backdrop of a unique gift.
 *
 * Source: https://core.telegram.org/bots/api#uniquegiftbackdropcolors
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UniqueGiftBackdropColors extends TelegramObject
{
  public function __construct(
    public readonly int $centerColor,
    public readonly int $edgeColor,
    public readonly int $symbolColor,
    public readonly int $textColor,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
