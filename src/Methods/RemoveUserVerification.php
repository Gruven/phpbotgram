<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Removes verification from a user who is currently verified on behalf of the organization represented by the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#removeuserverification
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class RemoveUserVerification extends TelegramMethod
{
  public const string ApiMethod = 'removeUserVerification';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
