<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes why a request was unsuccessful.
 *
 * Source: https://core.telegram.org/bots/api#responseparameters
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ResponseParameters extends TelegramObject
{
  public function __construct(
    public readonly ?int $migrateToChatId = null,
    public readonly ?int $retryAfter = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
