<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a task in a checklist.
 *
 * Source: https://core.telegram.org/bots/api#checklisttask
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChecklistTask extends TelegramObject
{
  /**
   * @param list<MessageEntity> $textEntities
   */
  public function __construct(
    public readonly int $id,
    public readonly string $text,
    public readonly ?array $textEntities = null,
    public readonly ?User $completedByUser = null,
    public readonly ?Chat $completedByChat = null,
    public readonly ?int $completionDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
