<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object contains information about one answer option in a poll.
 *
 * Source: https://core.telegram.org/bots/api#polloption
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PollOption extends TelegramObject
{
  /**
   * @param null|list<MessageEntity> $textEntities
   */
  public function __construct(
    public readonly string $persistentId,
    public readonly string $text,
    public readonly int $voterCount,
    public readonly ?array $textEntities = null,
    public readonly ?PollMedia $media = null,
    public readonly ?User $addedByUser = null,
    public readonly ?Chat $addedByChat = null,
    public readonly ?DateTime $additionDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
