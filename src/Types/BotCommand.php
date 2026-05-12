<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a bot command.
 *
 * Source: https://core.telegram.org/bots/api#botcommand
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommand extends MutableTelegramObject
{
  public function __construct(
    public readonly string $command,
    public readonly string $description,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
