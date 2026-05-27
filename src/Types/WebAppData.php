<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes data sent from a Web App to the bot.
 *
 * Source: https://core.telegram.org/bots/api#webappdata
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class WebAppData extends TelegramObject
{
  public function __construct(
    public readonly string $data,
    public readonly string $buttonText,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
