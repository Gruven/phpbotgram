<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a venue. By default, the venue will be sent by the user. Alternatively, you can use input_message_content to send a message with the specified content instead of the venue.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultvenue
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultVenue extends InlineQueryResult
{
  public function __construct(
    public readonly string $type,
    public readonly string $id,
    public readonly float $latitude,
    public readonly float $longitude,
    public readonly string $title,
    public readonly string $address,
    public readonly ?string $foursquareId = null,
    public readonly ?string $foursquareType = null,
    public readonly ?string $googlePlaceId = null,
    public readonly ?string $googlePlaceType = null,
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
