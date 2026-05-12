<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BotCommand;
use Gruven\PhpBotGram\Types\BotCommandScope;

/**
 * Use this method to get the current list of the bot's commands for the given scope and user language. Returns an Array of BotCommand objects. If commands aren't set, an empty list is returned.
 *
 * Source: https://core.telegram.org/bots/api#getmycommands
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<list<BotCommand>>
 */
final class GetMyCommands extends TelegramMethod
{
  public const string ApiMethod = 'getMyCommands';
  public const string ReturnsType = 'list:BotCommand';

  public function __construct(
    public readonly ?BotCommandScope $scope = null,
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
