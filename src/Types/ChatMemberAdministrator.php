<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a chat member that has some additional privileges.
 *
 * Source: https://core.telegram.org/bots/api#chatmemberadministrator
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatMemberAdministrator extends ChatMember
{
  public function __construct(
    public readonly string $status,
    public readonly User $user,
    public readonly bool $canBeEdited,
    public readonly bool $isAnonymous,
    public readonly bool $canManageChat,
    public readonly bool $canDeleteMessages,
    public readonly bool $canManageVideoChats,
    public readonly bool $canRestrictMembers,
    public readonly bool $canPromoteMembers,
    public readonly bool $canChangeInfo,
    public readonly bool $canInviteUsers,
    public readonly bool $canPostStories,
    public readonly bool $canEditStories,
    public readonly bool $canDeleteStories,
    public readonly ?bool $canPostMessages = null,
    public readonly ?bool $canEditMessages = null,
    public readonly ?bool $canPinMessages = null,
    public readonly ?bool $canManageTopics = null,
    public readonly ?bool $canManageDirectMessages = null,
    public readonly ?bool $canManageTags = null,
    public readonly ?string $customTitle = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
