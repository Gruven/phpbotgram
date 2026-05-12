<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\MessageEntity;

/**
 * Use this method to stream a partial message to a user while the message is being generated. Note that the streamed draft is ephemeral and acts as a temporary 30-second preview - once the output is finalized, you must call sendMessage with the complete message to persist it in the user's chat. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#sendmessagedraft
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SendMessageDraft extends TelegramMethod
{
  public const string ApiMethod = 'sendMessageDraft';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $chatId,
    public readonly int $draftId,
    public readonly ?int $messageThreadId = null,
    public readonly ?string $text = null,
    public readonly ?string $parseMode = null,
    /** @var list<MessageEntity> */
    public readonly ?array $entities = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
