<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

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
