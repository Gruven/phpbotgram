<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * The message was originally sent to a channel chat.
 *
 * Source: https://core.telegram.org/bots/api#messageoriginchannel
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageOriginChannel extends MessageOrigin
{
  public function __construct(
    public readonly string $type,
    public readonly DateTime $date,
    public readonly Chat $chat,
    public readonly int $messageId,
    public readonly ?string $authorSignature = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
