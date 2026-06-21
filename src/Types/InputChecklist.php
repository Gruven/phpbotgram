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
   * @param list<InputChecklistTask> $tasks
   * @param null|list<MessageEntity> $titleEntities
   */
  public function __construct(
    public readonly string $title,
    public readonly array $tasks,
    public readonly ?string $parseMode = null,
    public readonly ?array $titleEntities = null,
    public readonly ?bool $othersCanAddTasks = null,
    public readonly ?bool $othersCanMarkTasksAsDone = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
