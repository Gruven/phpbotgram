<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\InputChecklist;
use Gruven\PhpBotGram\Types\Message;

/**
 * Use this method to edit a checklist on behalf of a connected business account. On success, the edited Message is returned.
 *
 * Source: https://core.telegram.org/bots/api#editmessagechecklist
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class EditMessageChecklist extends TelegramMethod
{
  public const string ApiMethod = 'editMessageChecklist';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly int|string $chatId,
    public readonly int $messageId,
    public readonly InputChecklist $checklist,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
