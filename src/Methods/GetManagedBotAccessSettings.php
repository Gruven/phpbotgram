<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BotAccessSettings;

/**
 * Use this method to get the access settings of a managed bot. Returns a BotAccessSettings object on success.
 *
 * Source: https://core.telegram.org/bots/api#getmanagedbotaccesssettings
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<BotAccessSettings>
 */
final class GetManagedBotAccessSettings extends TelegramMethod
{
  public const string ApiMethod = 'getManagedBotAccessSettings';
  public const string ReturnsType = BotAccessSettings::class;

  public function __construct(
    public readonly int $userId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
