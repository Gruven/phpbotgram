<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a chat photo.
 *
 * Source: https://core.telegram.org/bots/api#chatphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatPhoto extends TelegramObject
{
  public function __construct(
    public readonly string $smallFileId,
    public readonly string $smallFileUniqueId,
    public readonly string $bigFileId,
    public readonly string $bigFileUniqueId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
