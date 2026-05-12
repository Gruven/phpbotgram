<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * The message was originally sent by a known user.
 *
 * Source: https://core.telegram.org/bots/api#messageoriginuser
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageOriginUser extends MessageOrigin
{
  public function __construct(
    public readonly DateTime $date,
    public readonly User $senderUser,
    public readonly string $type = 'user',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
