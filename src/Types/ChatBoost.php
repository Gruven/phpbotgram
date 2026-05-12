<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object contains information about a chat boost.
 *
 * Source: https://core.telegram.org/bots/api#chatboost
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBoost extends TelegramObject
{
  public function __construct(
    public readonly string $boostId,
    public readonly DateTime $addDate,
    public readonly DateTime $expirationDate,
    public readonly ChatBoostSource $source,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
