<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\UserProfileAudios;

/**
 * Use this method to get a list of profile audios for a user. Returns a UserProfileAudios object.
 *
 * Source: https://core.telegram.org/bots/api#getuserprofileaudios
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<UserProfileAudios>
 */
final class GetUserProfileAudios extends TelegramMethod
{
  public const string ApiMethod = 'getUserProfileAudios';
  public const string ReturnsType = UserProfileAudios::class;

  public function __construct(
    public readonly int $userId,
    public readonly ?int $offset = null,
    public readonly ?int $limit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
