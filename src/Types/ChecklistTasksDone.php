<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about checklist tasks marked as done or not done.
 *
 * Source: https://core.telegram.org/bots/api#checklisttasksdone
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChecklistTasksDone extends TelegramObject
{
  /**
   * @param null|list<int> $markedAsDoneTaskIds
   * @param null|list<int> $markedAsNotDoneTaskIds
   */
  public function __construct(
    public readonly ?Message $checklistMessage = null,
    public readonly ?array $markedAsDoneTaskIds = null,
    public readonly ?array $markedAsNotDoneTaskIds = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
