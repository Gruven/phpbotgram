<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Phase 1 stub — minimal fields for smoke testing. Phase 2 regenerates with the full
 * 90+ field surface from the schema. The chat field uses an array placeholder here;
 * Phase 2 ships a typed Chat object.
 */
final class Message extends TelegramObject
{
  /**
   * @param array<string, mixed> $chat
   */
  public function __construct(
    public readonly int $messageId,
    public readonly int $date,
    public readonly array $chat,
    public readonly ?string $text = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
