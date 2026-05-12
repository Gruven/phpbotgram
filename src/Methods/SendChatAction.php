<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method when you need to tell the user that something is happening on the bot's side. The status is set for 5 seconds or less (when a message arrives from your bot, Telegram clients clear its typing status). Returns True on success.
 * Example: The ImageBot needs some time to process a request and upload the image. Instead of sending a text message along the lines of 'Retrieving image, please wait…', the bot may use sendChatAction with action = upload_photo. The user will see a 'sending photo' status for the bot.
 * We only recommend using this method when a response from the bot will take a noticeable amount of time to arrive.
 *
 * Source: https://core.telegram.org/bots/api#sendchataction
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SendChatAction extends TelegramMethod
{
  public const string ApiMethod = 'sendChatAction';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $action,
    public readonly ?string $businessConnectionId = null,
    public readonly ?int $messageThreadId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
