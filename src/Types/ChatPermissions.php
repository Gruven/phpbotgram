<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes actions that a non-administrator user is allowed to take in a chat.
 *
 * Source: https://core.telegram.org/bots/api#chatpermissions
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatPermissions extends MutableTelegramObject
{
  public function __construct(
    public readonly ?bool $canSendMessages = null,
    public readonly ?bool $canSendAudios = null,
    public readonly ?bool $canSendDocuments = null,
    public readonly ?bool $canSendPhotos = null,
    public readonly ?bool $canSendVideos = null,
    public readonly ?bool $canSendVideoNotes = null,
    public readonly ?bool $canSendVoiceNotes = null,
    public readonly ?bool $canSendPolls = null,
    public readonly ?bool $canSendOtherMessages = null,
    public readonly ?bool $canAddWebPagePreviews = null,
    public readonly ?bool $canReactToMessages = null,
    public readonly ?bool $canEditTag = null,
    public readonly ?bool $canChangeInfo = null,
    public readonly ?bool $canInviteUsers = null,
    public readonly ?bool $canPinMessages = null,
    public readonly ?bool $canManageTopics = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
