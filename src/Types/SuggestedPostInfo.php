<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Contains information about a suggested post.
 *
 * Source: https://core.telegram.org/bots/api#suggestedpostinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuggestedPostInfo extends TelegramObject
{
  public function __construct(
    public readonly string $state,
    public readonly ?SuggestedPostPrice $price = null,
    public readonly ?DateTime $sendDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
