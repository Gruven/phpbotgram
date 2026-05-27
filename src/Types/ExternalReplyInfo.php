<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about a message that is being replied to, which may come from another chat or forum topic.
 *
 * Source: https://core.telegram.org/bots/api#externalreplyinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ExternalReplyInfo extends TelegramObject
{
  /**
   * @param list<PhotoSize> $photo
   */
  public function __construct(
    public readonly MessageOrigin $origin,
    public readonly ?Chat $chat = null,
    public readonly ?int $messageId = null,
    public readonly ?LinkPreviewOptions $linkPreviewOptions = null,
    public readonly ?Animation $animation = null,
    public readonly ?Audio $audio = null,
    public readonly ?Document $document = null,
    public readonly ?LivePhoto $livePhoto = null,
    public readonly ?PaidMediaInfo $paidMedia = null,
    public readonly ?array $photo = null,
    public readonly ?Sticker $sticker = null,
    public readonly ?Story $story = null,
    public readonly ?Video $video = null,
    public readonly ?VideoNote $videoNote = null,
    public readonly ?Voice $voice = null,
    public readonly ?bool $hasMediaSpoiler = null,
    public readonly ?Checklist $checklist = null,
    public readonly ?Contact $contact = null,
    public readonly ?Dice $dice = null,
    public readonly ?Game $game = null,
    public readonly ?Giveaway $giveaway = null,
    public readonly ?GiveawayWinners $giveawayWinners = null,
    public readonly ?Invoice $invoice = null,
    public readonly ?Location $location = null,
    public readonly ?Poll $poll = null,
    public readonly ?Venue $venue = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
