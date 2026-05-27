<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a Web App.
 *
 * Source: https://core.telegram.org/bots/api#webappinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class WebAppInfo extends TelegramObject
{
  public function __construct(
    public readonly string $url,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
