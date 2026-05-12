<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatPermissions;

/**
 * Use this method to set default chat permissions for all members. The bot must be an administrator in the group or a supergroup for this to work and must have the can_restrict_members administrator rights. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setchatpermissions
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetChatPermissions extends TelegramMethod
{
  public const string ApiMethod = 'setChatPermissions';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly ChatPermissions $permissions,
    public readonly ?bool $useIndependentChatPermissions = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
