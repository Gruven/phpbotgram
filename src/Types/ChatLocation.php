<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a location to which a chat is connected.
 *
 * Source: https://core.telegram.org/bots/api#chatlocation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatLocation extends TelegramObject
{
  public function __construct(
    public readonly Location $location,
    public readonly string $address,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
