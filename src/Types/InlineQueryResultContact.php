<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a contact with a phone number. By default, this contact will be sent by the user. Alternatively, you can use input_message_content to send a message with the specified content instead of the contact.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultcontact
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultContact extends InlineQueryResult
{
  public function __construct(
    public readonly string $type,
    public readonly string $id,
    public readonly string $phoneNumber,
    public readonly string $firstName,
    public readonly ?string $lastName = null,
    public readonly ?string $vcard = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    public readonly ?string $thumbnailUrl = null,
    public readonly ?int $thumbnailWidth = null,
    public readonly ?int $thumbnailHeight = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
