<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatPermissions;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Use this method to restrict a user in a supergroup. The bot must be an administrator in the supergroup for this to work and must have the appropriate administrator rights. Pass True for all permissions to lift restrictions from a user. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#restrictchatmember
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class RestrictChatMember extends TelegramMethod
{
  public const string ApiMethod = 'restrictChatMember';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $userId,
    public readonly ChatPermissions $permissions,
    public readonly ?bool $useIndependentChatPermissions = null,
    public readonly DateInterval|DateTime|int|null $untilDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
