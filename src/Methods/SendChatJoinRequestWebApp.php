<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to process a received chat join request query by showing a Mini App to the user before deciding the outcome. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#sendchatjoinrequestwebapp
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SendChatJoinRequestWebApp extends TelegramMethod
{
  public const string ApiMethod = 'sendChatJoinRequestWebApp';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $chatJoinRequestQueryId,
    public readonly string $webAppUrl,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
