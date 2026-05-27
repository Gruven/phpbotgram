<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a location on a map. By default, the location will be sent by the user. Alternatively, you can use input_message_content to send a message with the specified content instead of the location.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultlocation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultLocation extends InlineQueryResult
{
  public function __construct(
    public readonly string $id,
    public readonly float $latitude,
    public readonly float $longitude,
    public readonly string $title,
    public readonly string $type = 'location',
    public readonly ?float $horizontalAccuracy = null,
    public readonly ?int $livePeriod = null,
    public readonly ?int $heading = null,
    public readonly ?int $proximityAlertRadius = null,
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
