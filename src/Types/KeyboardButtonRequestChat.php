<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object defines the criteria used to request a suitable chat. Information about the selected chat will be shared with the bot when the corresponding button is pressed. The bot will be granted requested rights in the chat if appropriate..
 *
 * Source: https://core.telegram.org/bots/api#keyboardbuttonrequestchat
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class KeyboardButtonRequestChat extends TelegramObject
{
  public function __construct(
    public readonly int $requestId,
    public readonly bool $chatIsChannel,
    public readonly ?bool $chatIsForum = null,
    public readonly ?bool $chatHasUsername = null,
    public readonly ?bool $chatIsCreated = null,
    public readonly ?ChatAdministratorRights $userAdministratorRights = null,
    public readonly ?ChatAdministratorRights $botAdministratorRights = null,
    public readonly ?bool $botIsMember = null,
    public readonly ?bool $requestTitle = null,
    public readonly ?bool $requestUsername = null,
    public readonly ?bool $requestPhoto = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
