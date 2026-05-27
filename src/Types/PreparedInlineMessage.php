<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Describes an inline message to be sent by a user of a Mini App.
 *
 * Source: https://core.telegram.org/bots/api#preparedinlinemessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PreparedInlineMessage extends TelegramObject
{
  public function __construct(
    public readonly string $id,
    public readonly DateInterval|DateTime|int $expirationDate,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
