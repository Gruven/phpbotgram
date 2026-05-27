<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about the rejection of a suggested post.
 *
 * Source: https://core.telegram.org/bots/api#suggestedpostdeclined
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuggestedPostDeclined extends TelegramObject
{
  public function __construct(
    public readonly ?Message $suggestedPostMessage = null,
    public readonly ?string $comment = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
