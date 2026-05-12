<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about a payment refund for a suggested post.
 *
 * Source: https://core.telegram.org/bots/api#suggestedpostrefunded
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuggestedPostRefunded extends TelegramObject
{
  public function __construct(
    public readonly ?Message $suggestedPostMessage,
    public readonly string $reason,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
