<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\UserProfilePhotos;

/**
 * Use this method to get a list of profile pictures for a user. Returns a UserProfilePhotos object.
 *
 * Source: https://core.telegram.org/bots/api#getuserprofilephotos
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<UserProfilePhotos>
 */
final class GetUserProfilePhotos extends TelegramMethod
{
  public const string ApiMethod = 'getUserProfilePhotos';
  public const string ReturnsType = UserProfilePhotos::class;

  public function __construct(
    public readonly int $userId,
    public readonly ?int $offset = null,
    public readonly ?int $limit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
