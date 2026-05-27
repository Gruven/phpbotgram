<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the audios displayed on a user's profile.
 *
 * Source: https://core.telegram.org/bots/api#userprofileaudios
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UserProfileAudios extends TelegramObject
{
  /**
   * @param list<Audio> $audios
   */
  public function __construct(
    public readonly int $totalCount,
    public readonly array $audios,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
