<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes an inline message sent by a Web App on behalf of a user.
 *
 * Source: https://core.telegram.org/bots/api#sentwebappmessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SentWebAppMessage extends TelegramObject
{
  public function __construct(
    public readonly ?string $inlineMessageId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
