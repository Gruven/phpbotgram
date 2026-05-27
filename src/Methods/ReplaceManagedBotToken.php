<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to revoke the current token of a managed bot and generate a new one. Returns the new token as String on success.
 *
 * Source: https://core.telegram.org/bots/api#replacemanagedbottoken
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<string>
 */
final class ReplaceManagedBotToken extends TelegramMethod
{
  public const string ApiMethod = 'replaceManagedBotToken';
  public const string ReturnsType = 'string';

  public function __construct(
    public readonly int $userId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
