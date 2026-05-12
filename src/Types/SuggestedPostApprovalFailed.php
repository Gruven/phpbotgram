<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about the failed approval of a suggested post. Currently, only caused by insufficient user funds at the time of approval.
 *
 * Source: https://core.telegram.org/bots/api#suggestedpostapprovalfailed
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuggestedPostApprovalFailed extends TelegramObject
{
  public function __construct(
    public readonly ?Message $suggestedPostMessage,
    public readonly SuggestedPostPrice $price,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
