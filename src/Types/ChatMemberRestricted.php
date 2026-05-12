<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Represents a chat member that is under certain restrictions in the chat. Supergroups only.
 *
 * Source: https://core.telegram.org/bots/api#chatmemberrestricted
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatMemberRestricted extends ChatMember
{
  public function __construct(
    public readonly string $status,
    public readonly ?string $tag,
    public readonly User $user,
    public readonly bool $isMember,
    public readonly bool $canSendMessages,
    public readonly bool $canSendAudios,
    public readonly bool $canSendDocuments,
    public readonly bool $canSendPhotos,
    public readonly bool $canSendVideos,
    public readonly bool $canSendVideoNotes,
    public readonly bool $canSendVoiceNotes,
    public readonly bool $canSendPolls,
    public readonly bool $canSendOtherMessages,
    public readonly bool $canAddWebPagePreviews,
    public readonly bool $canReactToMessages,
    public readonly bool $canEditTag,
    public readonly bool $canChangeInfo,
    public readonly bool $canInviteUsers,
    public readonly bool $canPinMessages,
    public readonly bool $canManageTopics,
    public readonly DateTime $untilDate,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
