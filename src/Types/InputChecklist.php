<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a checklist to create.
 *
 * Source: https://core.telegram.org/bots/api#inputchecklist
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputChecklist extends TelegramObject
{
  /**
   * @param list<MessageEntity> $titleEntities
   * @param list<InputChecklistTask> $tasks
   */
  public function __construct(
    public readonly string $title,
    public readonly ?string $parseMode,
    public readonly ?array $titleEntities,
    public readonly array $tasks,
    public readonly ?bool $othersCanAddTasks = null,
    public readonly ?bool $othersCanMarkTasksAsDone = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
