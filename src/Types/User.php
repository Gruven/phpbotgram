<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\GetUserProfileAudios;
use Gruven\PhpBotGram\Methods\GetUserProfilePhotos;
use Gruven\PhpBotGram\Types\Shortcuts\UserShortcuts;

/**
 * This object represents a Telegram user or bot.
 *
 * Source: https://core.telegram.org/bots/api#user
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class User extends TelegramObject
{
  use UserShortcuts;

  public function __construct(
    public readonly int $id,
    public readonly bool $isBot,
    public readonly string $firstName,
    public readonly ?string $lastName = null,
    public readonly ?string $username = null,
    public readonly ?string $languageCode = null,
    public readonly ?bool $isPremium = null,
    public readonly ?bool $addedToAttachmentMenu = null,
    public readonly ?bool $canJoinGroups = null,
    public readonly ?bool $canReadAllGroupMessages = null,
    public readonly ?bool $supportsGuestQueries = null,
    public readonly ?bool $supportsInlineQueries = null,
    public readonly ?bool $canConnectToBusiness = null,
    public readonly ?bool $hasMainWebApp = null,
    public readonly ?bool $hasTopicsEnabled = null,
    public readonly ?bool $allowsUsersToCreateTopics = null,
    public readonly ?bool $canManageBots = null,
    public readonly ?bool $supportsJoinRequestQueries = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }

  public function getProfilePhotos(
    ?int $offset = null,
    ?int $limit = null,
  ): GetUserProfilePhotos {
    return new GetUserProfilePhotos(
      userId: $this->id,
      offset: $offset,
      limit: $limit,
      bot: $this->bot,
    );
  }

  public function getProfileAudios(
    ?int $offset = null,
    ?int $limit = null,
  ): GetUserProfileAudios {
    return new GetUserProfileAudios(
      userId: $this->id,
      offset: $offset,
      limit: $limit,
      bot: $this->bot,
    );
  }
}
