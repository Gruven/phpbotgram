<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Describes a service message about the approval of a suggested post.
 *
 * Source: https://core.telegram.org/bots/api#suggestedpostapproved
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuggestedPostApproved extends TelegramObject
{
  public function __construct(
    public readonly DateTime $sendDate,
    public readonly ?Message $suggestedPostMessage = null,
    public readonly ?SuggestedPostPrice $price = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
