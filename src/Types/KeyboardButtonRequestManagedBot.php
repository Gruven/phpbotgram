<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object defines the parameters for the creation of a managed bot. Information about the created bot will be shared with the bot using the update managed_bot and a Message with the field managed_bot_created.
 *
 * Source: https://core.telegram.org/bots/api#keyboardbuttonrequestmanagedbot
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class KeyboardButtonRequestManagedBot extends TelegramObject
{
  public function __construct(
    public readonly int $requestId,
    public readonly ?string $suggestedName = null,
    public readonly ?string $suggestedUsername = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
