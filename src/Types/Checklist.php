<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a checklist.
 *
 * Source: https://core.telegram.org/bots/api#checklist
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Checklist extends TelegramObject
{
  /**
   * @param list<ChecklistTask> $tasks
   * @param list<MessageEntity> $titleEntities
   */
  public function __construct(
    public readonly string $title,
    public readonly array $tasks,
    public readonly ?array $titleEntities = null,
    public readonly ?bool $othersCanAddTasks = null,
    public readonly ?bool $othersCanMarkTasksAsDone = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
