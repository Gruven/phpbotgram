<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to change the access settings of a managed bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setmanagedbotaccesssettings
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetManagedBotAccessSettings extends TelegramMethod
{
  public const string ApiMethod = 'setManagedBotAccessSettings';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly bool $isAccessRestricted,
    /** @var list<int> */
    public readonly ?array $addedUserIds = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
