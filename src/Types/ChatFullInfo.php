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
   * @param null|list<string> $activeUsernames
   * @param null|list<ReactionType> $availableReactions
   */
  public function __construct(
    int $id,
    string $type,
    public readonly int $accentColorId,
    public readonly int $maxReactionCount,
    public readonly AcceptedGiftTypes $acceptedGiftTypes,
    ?string $title = null,
    ?string $username = null,
    ?string $firstName = null,
    ?string $lastName = null,
    ?bool $isForum = null,
    ?bool $isDirectMessages = null,
    public readonly ?ChatPhoto $photo = null,
    public readonly ?array $activeUsernames = null,
    public readonly ?Birthdate $birthdate = null,
    public readonly ?BusinessIntro $businessIntro = null,
    public readonly ?BusinessLocation $businessLocation = null,
    public readonly ?BusinessOpeningHours $businessOpeningHours = null,
    public readonly ?Chat $personalChat = null,
    public readonly ?Chat $parentChat = null,
    public readonly ?array $availableReactions = null,
    public readonly ?string $backgroundCustomEmojiId = null,
    public readonly ?int $profileAccentColorId = null,
    public readonly ?string $profileBackgroundCustomEmojiId = null,
    public readonly ?string $emojiStatusCustomEmojiId = null,
    public readonly ?DateTime $emojiStatusExpirationDate = null,
    public readonly ?string $bio = null,
    public readonly ?bool $hasPrivateForwards = null,
    public readonly ?bool $hasRestrictedVoiceAndVideoMessages = null,
    public readonly ?bool $joinToSendMessages = null,
    public readonly ?bool $joinByRequest = null,
    public readonly ?string $description = null,
    public readonly ?string $inviteLink = null,
    public readonly ?Message $pinnedMessage = null,
    public readonly ?ChatPermissions $permissions = null,
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
    public readonly ?User $guardBot = null,
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
