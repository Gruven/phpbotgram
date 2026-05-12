<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represent a user's profile pictures.
 *
 * Source: https://core.telegram.org/bots/api#userprofilephotos
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UserProfilePhotos extends TelegramObject
{
  /**
   * @param list<list<PhotoSize>> $photos
   */
  public function __construct(
    public readonly int $totalCount,
    public readonly array $photos,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
