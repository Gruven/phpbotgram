<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to promote or demote a user in a supergroup or a channel. The bot must be an administrator in the chat for this to work and must have the appropriate administrator rights. Pass False for all boolean parameters to demote a user. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#promotechatmember
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class PromoteChatMember extends TelegramMethod
{
  public const string ApiMethod = 'promoteChatMember';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $userId,
    public readonly ?bool $isAnonymous = null,
    public readonly ?bool $canManageChat = null,
    public readonly ?bool $canDeleteMessages = null,
    public readonly ?bool $canManageVideoChats = null,
    public readonly ?bool $canRestrictMembers = null,
    public readonly ?bool $canPromoteMembers = null,
    public readonly ?bool $canChangeInfo = null,
    public readonly ?bool $canInviteUsers = null,
    public readonly ?bool $canPostStories = null,
    public readonly ?bool $canEditStories = null,
    public readonly ?bool $canDeleteStories = null,
    public readonly ?bool $canPostMessages = null,
    public readonly ?bool $canEditMessages = null,
    public readonly ?bool $canPinMessages = null,
    public readonly ?bool $canManageTopics = null,
    public readonly ?bool $canManageDirectMessages = null,
    public readonly ?bool $canManageTags = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
