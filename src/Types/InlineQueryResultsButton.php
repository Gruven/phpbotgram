<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a button to be shown above inline query results. You must use exactly one of the optional fields.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultsbutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultsButton extends TelegramObject
{
  public function __construct(
    public readonly string $text,
    public readonly ?WebAppInfo $webApp = null,
    public readonly ?string $startParameter = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
