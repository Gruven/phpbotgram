<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about the quoted part of a message that is replied to by the given message.
 *
 * Source: https://core.telegram.org/bots/api#textquote
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TextQuote extends TelegramObject
{
  /**
   * @param list<MessageEntity> $entities
   */
  public function __construct(
    public readonly string $text,
    public readonly ?array $entities,
    public readonly int $position,
    public readonly ?bool $isManual = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
