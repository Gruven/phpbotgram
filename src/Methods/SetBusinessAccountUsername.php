<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Changes the username of a managed business account. Requires the can_change_username business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setbusinessaccountusername
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetBusinessAccountUsername extends TelegramMethod
{
  public const string ApiMethod = 'setBusinessAccountUsername';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly ?string $username = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
