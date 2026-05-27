<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object contains information about a poll.
 *
 * Source: https://core.telegram.org/bots/api#poll
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Poll extends TelegramObject
{
  /**
   * @param list<PollOption> $options
   * @param list<MessageEntity> $questionEntities
   * @param list<string> $countryCodes
   * @param list<int> $correctOptionIds
   * @param list<MessageEntity> $explanationEntities
   * @param list<MessageEntity> $descriptionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $question,
    public readonly array $options,
    public readonly int $totalVoterCount,
    public readonly bool $isClosed,
    public readonly bool $isAnonymous,
    public readonly string $type,
    public readonly bool $allowsMultipleAnswers,
    public readonly bool $allowsRevoting,
    public readonly bool $membersOnly,
    public readonly ?array $questionEntities = null,
    public readonly ?array $countryCodes = null,
    public readonly ?array $correctOptionIds = null,
    public readonly ?string $explanation = null,
    public readonly ?array $explanationEntities = null,
    public readonly ?PollMedia $explanationMedia = null,
    public readonly ?int $openPeriod = null,
    public readonly ?DateTime $closeDate = null,
    public readonly ?string $description = null,
    public readonly ?array $descriptionEntities = null,
    public readonly ?PollMedia $media = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
