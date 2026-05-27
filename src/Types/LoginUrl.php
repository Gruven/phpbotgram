<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a parameter of the inline keyboard button used to automatically authorize a user. Serves as a great replacement for the Telegram Login Widget when the user is coming from Telegram. All the user needs to do is tap/click a button and confirm that they want to log in:
 * Telegram apps support these buttons as of version 5.7.
 * Sample bot: @discussbot
 *
 * Source: https://core.telegram.org/bots/api#loginurl
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class LoginUrl extends TelegramObject
{
  public function __construct(
    public readonly string $url,
    public readonly ?string $forwardText = null,
    public readonly ?string $botUsername = null,
    public readonly ?bool $requestWriteAccess = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
