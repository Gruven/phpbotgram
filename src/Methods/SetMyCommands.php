<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BotCommand;
use Gruven\PhpBotGram\Types\BotCommandScope;

/**
 * Use this method to change the list of the bot's commands. See this manual for more details about bot commands. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setmycommands
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetMyCommands extends TelegramMethod
{
  public const string ApiMethod = 'setMyCommands';
  public const string ReturnsType = 'bool';

  public function __construct(
    /** @var list<BotCommand> */
    public readonly array $commands,
    public readonly ?BotCommandScope $scope = null,
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
