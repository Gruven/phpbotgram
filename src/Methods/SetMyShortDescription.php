<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to change the bot's short description, which is shown on the bot's profile page and is sent together with the link when users share the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setmyshortdescription
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetMyShortDescription extends TelegramMethod
{
  public const string ApiMethod = 'setMyShortDescription';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly ?string $shortDescription = null,
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
