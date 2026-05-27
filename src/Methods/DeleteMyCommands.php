<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BotCommandScope;

/**
 * Use this method to delete the list of the bot's commands for the given scope and user language. After deletion, higher level commands will be shown to affected users. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletemycommands
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteMyCommands extends TelegramMethod
{
  public const string ApiMethod = 'deleteMyCommands';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly ?BotCommandScope $scope = null,
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
