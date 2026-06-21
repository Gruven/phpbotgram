<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about a chat that was shared with the bot using a KeyboardButtonRequestChat button.
 *
 * Source: https://core.telegram.org/bots/api#chatshared
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatShared extends TelegramObject
{
  /**
   * @param null|list<PhotoSize> $photo
   */
  public function __construct(
    public readonly int $requestId,
    public readonly int $chatId,
    public readonly ?string $title = null,
    public readonly ?string $username = null,
    public readonly ?array $photo = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
