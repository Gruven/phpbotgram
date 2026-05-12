<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\WebhookInfo;

/**
 * Use this method to get current webhook status. Requires no parameters. On success, returns a WebhookInfo object. If the bot is using getUpdates, will return an object with the url field empty.
 *
 * Source: https://core.telegram.org/bots/api#getwebhookinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<WebhookInfo>
 */
final class GetWebhookInfo extends TelegramMethod
{
  public const string ApiMethod = 'getWebhookInfo';
  public const string ReturnsType = WebhookInfo::class;

  public function __construct(?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
