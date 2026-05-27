<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Changes the bio of a managed business account. Requires the can_change_bio business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setbusinessaccountbio
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetBusinessAccountBio extends TelegramMethod
{
  public const string ApiMethod = 'setBusinessAccountBio';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly ?string $bio = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
