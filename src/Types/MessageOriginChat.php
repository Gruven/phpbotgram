<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * The message was originally sent on behalf of a chat to a group chat.
 *
 * Source: https://core.telegram.org/bots/api#messageoriginchat
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageOriginChat extends MessageOrigin
{
  public function __construct(
    public readonly DateTime $date,
    public readonly Chat $senderChat,
    public readonly string $type = 'chat',
    public readonly ?string $authorSignature = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
