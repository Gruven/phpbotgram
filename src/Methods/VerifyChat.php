<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Verifies a chat on behalf of the organization which is represented by the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#verifychat
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class VerifyChat extends TelegramMethod
{
  public const string ApiMethod = 'verifyChat';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly ?string $customDescription = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
