<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Delete messages on behalf of a business account. Requires the can_delete_sent_messages business bot right to delete messages sent by the bot itself, or the can_delete_all_messages business bot right to delete any message. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletebusinessmessages
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteBusinessMessages extends TelegramMethod
{
  public const string ApiMethod = 'deleteBusinessMessages';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    /** @var list<int> */
    public readonly array $messageIds,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
