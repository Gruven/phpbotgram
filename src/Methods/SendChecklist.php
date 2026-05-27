<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\InputChecklist;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\ReplyParameters;

/**
 * Use this method to send a checklist on behalf of a connected business account. On success, the sent Message is returned.
 *
 * Source: https://core.telegram.org/bots/api#sendchecklist
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class SendChecklist extends TelegramMethod
{
  public const string ApiMethod = 'sendChecklist';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly int|string $chatId,
    public readonly InputChecklist $checklist,
    public readonly ?bool $disableNotification = null,
    public readonly ?bool $protectContent = null,
    public readonly ?string $messageEffectId = null,
    public readonly ?ReplyParameters $replyParameters = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
