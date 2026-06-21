<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputRichMessage;

/**
 * Use this method to stream a partial rich message to a user while the message is being generated. Note that the streamed draft is ephemeral and acts as a temporary 30-second preview - once the output is finalized, you must call sendRichMessage with the complete message to persist it in the user's chat. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#sendrichmessagedraft
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SendRichMessageDraft extends TelegramMethod
{
  public const string ApiMethod = 'sendRichMessageDraft';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $chatId,
    public readonly int $draftId,
    public readonly InputRichMessage $richMessage,
    public readonly ?int $messageThreadId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
