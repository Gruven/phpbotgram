<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Allows the bot to cancel or re-enable extension of a subscription paid in Telegram Stars. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#edituserstarsubscription
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class EditUserStarSubscription extends TelegramMethod
{
  public const string ApiMethod = 'editUserStarSubscription';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly string $telegramPaymentChargeId,
    public readonly bool $isCanceled,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
