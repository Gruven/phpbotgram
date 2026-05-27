<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a task to add to a checklist.
 *
 * Source: https://core.telegram.org/bots/api#inputchecklisttask
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputChecklistTask extends TelegramObject
{
  /**
   * @param list<MessageEntity> $textEntities
   */
  public function __construct(
    public readonly int $id,
    public readonly string $text,
    public readonly ?string $parseMode = null,
    public readonly ?array $textEntities = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
