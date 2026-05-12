<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to get the token of a managed bot. Returns the token as String on success.
 *
 * Source: https://core.telegram.org/bots/api#getmanagedbottoken
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<string>
 */
final class GetManagedBotToken extends TelegramMethod
{
  public const string ApiMethod = 'getManagedBotToken';
  public const string ReturnsType = 'string';

  public function __construct(
    public readonly int $userId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
