<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the rating of a user based on their Telegram Star spendings.
 *
 * Source: https://core.telegram.org/bots/api#userrating
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UserRating extends TelegramObject
{
  public function __construct(
    public readonly int $level,
    public readonly int $rating,
    public readonly int $currentLevelRating,
    public readonly ?int $nextLevelRating = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
