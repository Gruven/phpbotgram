<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to remove webhook integration if you decide to switch back to getUpdates. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletewebhook
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteWebhook extends TelegramMethod
{
  public const string ApiMethod = 'deleteWebhook';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly ?bool $dropPendingUpdates = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
