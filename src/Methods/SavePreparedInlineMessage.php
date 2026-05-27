<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineQueryResult;
use Gruven\PhpBotGram\Types\PreparedInlineMessage;

/**
 * Stores a message that can be sent by a user of a Mini App. Returns a PreparedInlineMessage object.
 *
 * Source: https://core.telegram.org/bots/api#savepreparedinlinemessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<PreparedInlineMessage>
 */
final class SavePreparedInlineMessage extends TelegramMethod
{
  public const string ApiMethod = 'savePreparedInlineMessage';
  public const string ReturnsType = PreparedInlineMessage::class;

  public function __construct(
    public readonly int $userId,
    public readonly InlineQueryResult $result,
    public readonly ?bool $allowUserChats = null,
    public readonly ?bool $allowBotChats = null,
    public readonly ?bool $allowGroupChats = null,
    public readonly ?bool $allowChannelChats = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
