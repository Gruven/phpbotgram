<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about tasks added to a checklist.
 *
 * Source: https://core.telegram.org/bots/api#checklisttasksadded
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChecklistTasksAdded extends TelegramObject
{
  /**
   * @param list<ChecklistTask> $tasks
   */
  public function __construct(
    public readonly array $tasks,
    public readonly ?Message $checklistMessage = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
