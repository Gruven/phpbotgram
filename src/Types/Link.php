<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an HTTP link.
 *
 * Source: https://core.telegram.org/bots/api#link
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Link extends TelegramObject
{
  public function __construct(
    public readonly string $url,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
