<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Marks incoming message as read on behalf of a business account. Requires the can_read_messages business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#readbusinessmessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class ReadBusinessMessage extends TelegramMethod
{
  public const string ApiMethod = 'readBusinessMessage';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly int $chatId,
    public readonly int $messageId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
