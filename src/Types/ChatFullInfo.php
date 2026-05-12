<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object contains full information about a chat.
 *
 * Source: https://core.telegram.org/bots/api#chatfullinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatFullInfo extends Chat
{
  /**
   * @param list<string> $activeUsernames
   * @param list<ReactionType> $availableReactions
   */
  public function __construct(
    int $id,
    string $type,
    ?string $title,
    ?string $username,
    ?string $firstName,
    ?string $lastName,
    ?bool $isForum,
    ?bool $isDirectMessages,
    public readonly int $accentColorId,
    public readonly int $maxReactionCount,
    public readonly ?ChatPhoto $photo,
    public readonly ?array $activeUsernames,
    public readonly ?Birthdate $birthdate,
    public readonly ?BusinessIntro $businessIntro,
    public readonly ?BusinessLocation $businessLocation,
    public readonly ?BusinessOpeningHours $businessOpeningHours,
    public readonly ?Chat $personalChat,
    public readonly ?Chat $parentChat,
    public readonly ?array $availableReactions,
    public readonly ?string $backgroundCustomEmojiId,
    public readonly ?int $profileAccentColorId,
    public readonly ?string $profileBackgroundCustomEmojiId,
    public readonly ?string $emojiStatusCustomEmojiId,
    public readonly ?DateTime $emojiStatusExpirationDate,
    public readonly ?string $bio,
    public readonly ?bool $hasPrivateForwards,
    public readonly ?bool $hasRestrictedVoiceAndVideoMessages,
    public readonly ?bool $joinToSendMessages,
    public readonly ?bool $joinByRequest,
    public readonly ?string $description,
    public readonly ?string $inviteLink,
    public readonly ?Message $pinnedMessage,
    public readonly ?ChatPermissions $permissions,
    public readonly AcceptedGiftTypes $acceptedGiftTypes,
    public readonly ?bool $canSendPaidMedia = null,
    public readonly ?int $slowModeDelay = null,
    public readonly ?int $unrestrictBoostCount = null,
    public readonly ?int $messageAutoDeleteTime = null,
    public readonly ?bool $hasAggressiveAntiSpamEnabled = null,
    public readonly ?bool $hasHiddenMembers = null,
    public readonly ?bool $hasProtectedContent = null,
    public readonly ?bool $hasVisibleHistory = null,
    public readonly ?string $stickerSetName = null,
    public readonly ?bool $canSetStickerSet = null,
    public readonly ?string $customEmojiStickerSetName = null,
    public readonly ?int $linkedChatId = null,
    public readonly ?ChatLocation $location = null,
    public readonly ?UserRating $rating = null,
    public readonly ?Audio $firstProfileAudio = null,
    public readonly ?UniqueGiftColors $uniqueGiftColors = null,
    public readonly ?int $paidMessageStarCount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct(
      id: $id,
      type: $type,
      title: $title,
      username: $username,
      firstName: $firstName,
      lastName: $lastName,
      isForum: $isForum,
      isDirectMessages: $isDirectMessages,
      bot: $bot,
    );
  }
}
