<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object defines the criteria used to request suitable users. Information about the selected users will be shared with the bot when the corresponding button is pressed.
 *
 * Source: https://core.telegram.org/bots/api#keyboardbuttonrequestusers
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class KeyboardButtonRequestUsers extends TelegramObject
{
  public function __construct(
    public readonly int $requestId,
    public readonly ?bool $userIsBot = null,
    public readonly ?bool $userIsPremium = null,
    public readonly ?int $maxQuantity = null,
    public readonly ?bool $requestName = null,
    public readonly ?bool $requestUsername = null,
    public readonly ?bool $requestPhoto = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
