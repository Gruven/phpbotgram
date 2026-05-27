<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\BanChatMember;
use Gruven\PhpBotGram\Methods\BanChatSenderChat;
use Gruven\PhpBotGram\Methods\CreateChatInviteLink;
use Gruven\PhpBotGram\Methods\DeleteChatPhoto;
use Gruven\PhpBotGram\Methods\DeleteChatStickerSet;
use Gruven\PhpBotGram\Methods\DeleteMessage;
use Gruven\PhpBotGram\Methods\EditChatInviteLink;
use Gruven\PhpBotGram\Methods\ExportChatInviteLink;
use Gruven\PhpBotGram\Methods\GetChatAdministrators;
use Gruven\PhpBotGram\Methods\GetChatMember;
use Gruven\PhpBotGram\Methods\GetChatMemberCount;
use Gruven\PhpBotGram\Methods\LeaveChat;
use Gruven\PhpBotGram\Methods\PinChatMessage;
use Gruven\PhpBotGram\Methods\PromoteChatMember;
use Gruven\PhpBotGram\Methods\RestrictChatMember;
use Gruven\PhpBotGram\Methods\RevokeChatInviteLink;
use Gruven\PhpBotGram\Methods\SendChatAction;
use Gruven\PhpBotGram\Methods\SetChatAdministratorCustomTitle;
use Gruven\PhpBotGram\Methods\SetChatDescription;
use Gruven\PhpBotGram\Methods\SetChatMemberTag;
use Gruven\PhpBotGram\Methods\SetChatPermissions;
use Gruven\PhpBotGram\Methods\SetChatPhoto;
use Gruven\PhpBotGram\Methods\SetChatStickerSet;
use Gruven\PhpBotGram\Methods\SetChatTitle;
use Gruven\PhpBotGram\Methods\UnbanChatMember;
use Gruven\PhpBotGram\Methods\UnbanChatSenderChat;
use Gruven\PhpBotGram\Methods\UnpinAllChatMessages;
use Gruven\PhpBotGram\Methods\UnpinAllGeneralForumTopicMessages;
use Gruven\PhpBotGram\Methods\UnpinChatMessage;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Shortcuts\ChatShortcuts;

/**
 * This object represents a chat.
 *
 * Source: https://core.telegram.org/bots/api#chat
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
class Chat extends TelegramObject
{
  use ChatShortcuts;

  public function __construct(
    public readonly int $id,
    public readonly string $type,
    public readonly ?string $title = null,
    public readonly ?string $username = null,
    public readonly ?string $firstName = null,
    public readonly ?string $lastName = null,
    public readonly ?bool $isForum = null,
    public readonly ?bool $isDirectMessages = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }

  public function banSenderChat(
    int $senderChatId,
  ): BanChatSenderChat {
    return new BanChatSenderChat(
      chatId: $this->id,
      senderChatId: $senderChatId,
      bot: $this->bot,
    );
  }

  public function unbanSenderChat(
    int $senderChatId,
  ): UnbanChatSenderChat {
    return new UnbanChatSenderChat(
      chatId: $this->id,
      senderChatId: $senderChatId,
      bot: $this->bot,
    );
  }

  public function getAdministrators(
    ?bool $returnBots = null,
  ): GetChatAdministrators {
    return new GetChatAdministrators(
      chatId: $this->id,
      returnBots: $returnBots,
      bot: $this->bot,
    );
  }

  public function deleteMessage(
    int $messageId,
  ): DeleteMessage {
    return new DeleteMessage(
      chatId: $this->id,
      messageId: $messageId,
      bot: $this->bot,
    );
  }

  public function revokeInviteLink(
    string $inviteLink,
  ): RevokeChatInviteLink {
    return new RevokeChatInviteLink(
      chatId: $this->id,
      inviteLink: $inviteLink,
      bot: $this->bot,
    );
  }

  public function editInviteLink(
    string $inviteLink,
    ?string $name = null,
    DateInterval|DateTime|int|null $expireDate = null,
    ?int $memberLimit = null,
    ?bool $createsJoinRequest = null,
  ): EditChatInviteLink {
    return new EditChatInviteLink(
      chatId: $this->id,
      inviteLink: $inviteLink,
      name: $name,
      expireDate: $expireDate,
      memberLimit: $memberLimit,
      createsJoinRequest: $createsJoinRequest,
      bot: $this->bot,
    );
  }

  public function createInviteLink(
    ?string $name = null,
    DateInterval|DateTime|int|null $expireDate = null,
    ?int $memberLimit = null,
    ?bool $createsJoinRequest = null,
  ): CreateChatInviteLink {
    return new CreateChatInviteLink(
      chatId: $this->id,
      name: $name,
      expireDate: $expireDate,
      memberLimit: $memberLimit,
      createsJoinRequest: $createsJoinRequest,
      bot: $this->bot,
    );
  }

  public function exportInviteLink(
  ): ExportChatInviteLink {
    return new ExportChatInviteLink(
      chatId: $this->id,
      bot: $this->bot,
    );
  }

  public function do(
    string $action,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
  ): SendChatAction {
    return new SendChatAction(
      businessConnectionId: $businessConnectionId,
      chatId: $this->id,
      messageThreadId: $messageThreadId,
      action: $action,
      bot: $this->bot,
    );
  }

  public function deleteStickerSet(
  ): DeleteChatStickerSet {
    return new DeleteChatStickerSet(
      chatId: $this->id,
      bot: $this->bot,
    );
  }

  public function setStickerSet(
    string $stickerSetName,
  ): SetChatStickerSet {
    return new SetChatStickerSet(
      chatId: $this->id,
      stickerSetName: $stickerSetName,
      bot: $this->bot,
    );
  }

  public function getMember(
    int $userId,
  ): GetChatMember {
    return new GetChatMember(
      chatId: $this->id,
      userId: $userId,
      bot: $this->bot,
    );
  }

  public function getMemberCount(
  ): GetChatMemberCount {
    return new GetChatMemberCount(
      chatId: $this->id,
      bot: $this->bot,
    );
  }

  public function leave(
  ): LeaveChat {
    return new LeaveChat(
      chatId: $this->id,
      bot: $this->bot,
    );
  }

  public function unpinAllMessages(
  ): UnpinAllChatMessages {
    return new UnpinAllChatMessages(
      chatId: $this->id,
      bot: $this->bot,
    );
  }

  public function unpinMessage(
    ?string $businessConnectionId = null,
    ?int $messageId = null,
  ): UnpinChatMessage {
    return new UnpinChatMessage(
      businessConnectionId: $businessConnectionId,
      chatId: $this->id,
      messageId: $messageId,
      bot: $this->bot,
    );
  }

  public function pinMessage(
    int $messageId,
    ?string $businessConnectionId = null,
    ?bool $disableNotification = null,
  ): PinChatMessage {
    return new PinChatMessage(
      businessConnectionId: $businessConnectionId,
      chatId: $this->id,
      messageId: $messageId,
      disableNotification: $disableNotification,
      bot: $this->bot,
    );
  }

  public function setAdministratorCustomTitle(
    int $userId,
    string $customTitle,
  ): SetChatAdministratorCustomTitle {
    return new SetChatAdministratorCustomTitle(
      chatId: $this->id,
      userId: $userId,
      customTitle: $customTitle,
      bot: $this->bot,
    );
  }

  public function setMemberTag(
    int $userId,
    ?string $tag = null,
  ): SetChatMemberTag {
    return new SetChatMemberTag(
      chatId: $this->id,
      userId: $userId,
      tag: $tag,
      bot: $this->bot,
    );
  }

  public function setPermissions(
    ChatPermissions $permissions,
    ?bool $useIndependentChatPermissions = null,
  ): SetChatPermissions {
    return new SetChatPermissions(
      chatId: $this->id,
      permissions: $permissions,
      useIndependentChatPermissions: $useIndependentChatPermissions,
      bot: $this->bot,
    );
  }

  public function promote(
    int $userId,
    ?bool $isAnonymous = null,
    ?bool $canManageChat = null,
    ?bool $canDeleteMessages = null,
    ?bool $canManageVideoChats = null,
    ?bool $canRestrictMembers = null,
    ?bool $canPromoteMembers = null,
    ?bool $canChangeInfo = null,
    ?bool $canInviteUsers = null,
    ?bool $canPostStories = null,
    ?bool $canEditStories = null,
    ?bool $canDeleteStories = null,
    ?bool $canPostMessages = null,
    ?bool $canEditMessages = null,
    ?bool $canPinMessages = null,
    ?bool $canManageTopics = null,
    ?bool $canManageDirectMessages = null,
    ?bool $canManageTags = null,
  ): PromoteChatMember {
    return new PromoteChatMember(
      chatId: $this->id,
      userId: $userId,
      isAnonymous: $isAnonymous,
      canManageChat: $canManageChat,
      canDeleteMessages: $canDeleteMessages,
      canManageVideoChats: $canManageVideoChats,
      canRestrictMembers: $canRestrictMembers,
      canPromoteMembers: $canPromoteMembers,
      canChangeInfo: $canChangeInfo,
      canInviteUsers: $canInviteUsers,
      canPostStories: $canPostStories,
      canEditStories: $canEditStories,
      canDeleteStories: $canDeleteStories,
      canPostMessages: $canPostMessages,
      canEditMessages: $canEditMessages,
      canPinMessages: $canPinMessages,
      canManageTopics: $canManageTopics,
      canManageDirectMessages: $canManageDirectMessages,
      canManageTags: $canManageTags,
      bot: $this->bot,
    );
  }

  public function restrict(
    int $userId,
    ChatPermissions $permissions,
    ?bool $useIndependentChatPermissions = null,
    DateInterval|DateTime|int|null $untilDate = null,
  ): RestrictChatMember {
    return new RestrictChatMember(
      chatId: $this->id,
      userId: $userId,
      permissions: $permissions,
      useIndependentChatPermissions: $useIndependentChatPermissions,
      untilDate: $untilDate,
      bot: $this->bot,
    );
  }

  public function unban(
    int $userId,
    ?bool $onlyIfBanned = null,
  ): UnbanChatMember {
    return new UnbanChatMember(
      chatId: $this->id,
      userId: $userId,
      onlyIfBanned: $onlyIfBanned,
      bot: $this->bot,
    );
  }

  public function ban(
    int $userId,
    DateInterval|DateTime|int|null $untilDate = null,
    ?bool $revokeMessages = null,
  ): BanChatMember {
    return new BanChatMember(
      chatId: $this->id,
      userId: $userId,
      untilDate: $untilDate,
      revokeMessages: $revokeMessages,
      bot: $this->bot,
    );
  }

  public function setDescription(
    ?string $description = null,
  ): SetChatDescription {
    return new SetChatDescription(
      chatId: $this->id,
      description: $description,
      bot: $this->bot,
    );
  }

  public function setTitle(
    string $title,
  ): SetChatTitle {
    return new SetChatTitle(
      chatId: $this->id,
      title: $title,
      bot: $this->bot,
    );
  }

  public function deletePhoto(
  ): DeleteChatPhoto {
    return new DeleteChatPhoto(
      chatId: $this->id,
      bot: $this->bot,
    );
  }

  public function setPhoto(
    InputFile $photo,
  ): SetChatPhoto {
    return new SetChatPhoto(
      chatId: $this->id,
      photo: $photo,
      bot: $this->bot,
    );
  }

  public function unpinAllGeneralForumTopicMessages(
  ): UnpinAllGeneralForumTopicMessages {
    return new UnpinAllGeneralForumTopicMessages(
      chatId: $this->id,
      bot: $this->bot,
    );
  }
}
