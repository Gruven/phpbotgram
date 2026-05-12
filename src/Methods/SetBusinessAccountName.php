<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Changes the first and last name of a managed business account. Requires the can_change_name business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setbusinessaccountname
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetBusinessAccountName extends TelegramMethod
{
  public const string ApiMethod = 'setBusinessAccountName';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly string $firstName,
    public readonly ?string $lastName = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
