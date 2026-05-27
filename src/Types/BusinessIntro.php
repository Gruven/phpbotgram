<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Contains information about the start page settings of a Telegram Business account.
 *
 * Source: https://core.telegram.org/bots/api#businessintro
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BusinessIntro extends TelegramObject
{
  public function __construct(
    public readonly ?string $title = null,
    public readonly ?string $message = null,
    public readonly ?Sticker $sticker = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
