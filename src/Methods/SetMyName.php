<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to change the bot's name. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setmyname
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetMyName extends TelegramMethod
{
  public const string ApiMethod = 'setMyName';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly ?string $name = null,
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
