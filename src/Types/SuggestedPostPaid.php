<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about a successful payment for a suggested post.
 *
 * Source: https://core.telegram.org/bots/api#suggestedpostpaid
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuggestedPostPaid extends TelegramObject
{
  public function __construct(
    public readonly string $currency,
    public readonly ?Message $suggestedPostMessage = null,
    public readonly ?int $amount = null,
    public readonly ?StarAmount $starAmount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
