<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * The message was originally sent by an unknown user.
 *
 * Source: https://core.telegram.org/bots/api#messageoriginhiddenuser
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageOriginHiddenUser extends MessageOrigin
{
  public function __construct(
    public readonly DateTime $date,
    public readonly string $senderUserName,
    public readonly string $type = 'hidden_user',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
