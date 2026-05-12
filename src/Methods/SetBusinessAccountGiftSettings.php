<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\AcceptedGiftTypes;

/**
 * Changes the privacy settings pertaining to incoming gifts in a managed business account. Requires the can_change_gift_settings business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setbusinessaccountgiftsettings
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetBusinessAccountGiftSettings extends TelegramMethod
{
  public const string ApiMethod = 'setBusinessAccountGiftSettings';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly bool $showGiftButton,
    public readonly AcceptedGiftTypes $acceptedGiftTypes,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
