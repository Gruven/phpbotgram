<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to delete multiple messages simultaneously. If some of the specified messages can't be found, they are skipped. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletemessages
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteMessages extends TelegramMethod
{
  public const string ApiMethod = 'deleteMessages';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    /** @var list<int> */
    public readonly array $messageIds,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
