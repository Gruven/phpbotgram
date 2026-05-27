<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to change the bot's description, which is shown in the chat with the bot if the chat is empty. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setmydescription
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetMyDescription extends TelegramMethod
{
  public const string ApiMethod = 'setMyDescription';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly ?string $description = null,
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
