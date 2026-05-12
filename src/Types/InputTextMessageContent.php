<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the content of a text message to be sent as the result of an inline query.
 *
 * Source: https://core.telegram.org/bots/api#inputtextmessagecontent
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputTextMessageContent extends InputMessageContent
{
  /**
   * @param list<MessageEntity> $entities
   */
  public function __construct(
    public readonly string $messageText,
    public readonly ?string $parseMode = null,
    public readonly ?array $entities = null,
    public readonly ?LinkPreviewOptions $linkPreviewOptions = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
