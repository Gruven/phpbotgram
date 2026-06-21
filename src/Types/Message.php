<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Methods\AnswerGuestQuery;
use Gruven\PhpBotGram\Methods\CopyMessage;
use Gruven\PhpBotGram\Methods\DeleteMessage;
use Gruven\PhpBotGram\Methods\EditMessageCaption;
use Gruven\PhpBotGram\Methods\EditMessageLiveLocation;
use Gruven\PhpBotGram\Methods\EditMessageMedia;
use Gruven\PhpBotGram\Methods\EditMessageReplyMarkup;
use Gruven\PhpBotGram\Methods\EditMessageText;
use Gruven\PhpBotGram\Methods\ForwardMessage;
use Gruven\PhpBotGram\Methods\PinChatMessage;
use Gruven\PhpBotGram\Methods\SendAnimation;
use Gruven\PhpBotGram\Methods\SendAudio;
use Gruven\PhpBotGram\Methods\SendContact;
use Gruven\PhpBotGram\Methods\SendDice;
use Gruven\PhpBotGram\Methods\SendDocument;
use Gruven\PhpBotGram\Methods\SendGame;
use Gruven\PhpBotGram\Methods\SendInvoice;
use Gruven\PhpBotGram\Methods\SendLocation;
use Gruven\PhpBotGram\Methods\SendMediaGroup;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Methods\SendPaidMedia;
use Gruven\PhpBotGram\Methods\SendPhoto;
use Gruven\PhpBotGram\Methods\SendPoll;
use Gruven\PhpBotGram\Methods\SendRichMessage;
use Gruven\PhpBotGram\Methods\SendSticker;
use Gruven\PhpBotGram\Methods\SendVenue;
use Gruven\PhpBotGram\Methods\SendVideo;
use Gruven\PhpBotGram\Methods\SendVideoNote;
use Gruven\PhpBotGram\Methods\SendVoice;
use Gruven\PhpBotGram\Methods\SetMessageReaction;
use Gruven\PhpBotGram\Methods\StopMessageLiveLocation;
use Gruven\PhpBotGram\Methods\UnpinChatMessage;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Shortcuts\MessageShortcuts;
use LogicException;

/**
 * This object represents a message.
 *
 * Source: https://core.telegram.org/bots/api#message
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Message extends MaybeInaccessibleMessage
{
  use MessageShortcuts;

  /** @var array<string, string> */
  public const array WireNames = [
    'fromUser' => 'from',
  ];

  /**
   * @param null|list<MessageEntity> $entities
   * @param null|list<PhotoSize> $photo
   * @param null|list<MessageEntity> $captionEntities
   * @param null|list<User> $newChatMembers
   * @param null|list<PhotoSize> $newChatPhoto
   */
  public function __construct(
    public readonly int $messageId,
    public readonly DateTime $date,
    public readonly Chat $chat,
    public readonly ?int $messageThreadId = null,
    public readonly ?DirectMessagesTopic $directMessagesTopic = null,
    public readonly ?User $fromUser = null,
    public readonly ?Chat $senderChat = null,
    public readonly ?int $senderBoostCount = null,
    public readonly ?User $senderBusinessBot = null,
    public readonly ?string $senderTag = null,
    public readonly ?string $guestQueryId = null,
    public readonly ?string $businessConnectionId = null,
    public readonly ?MessageOrigin $forwardOrigin = null,
    public readonly ?bool $isTopicMessage = null,
    public readonly ?bool $isAutomaticForward = null,
    public readonly ?Message $replyToMessage = null,
    public readonly ?ExternalReplyInfo $externalReply = null,
    public readonly ?TextQuote $quote = null,
    public readonly ?Story $replyToStory = null,
    public readonly ?int $replyToChecklistTaskId = null,
    public readonly ?string $replyToPollOptionId = null,
    public readonly ?User $viaBot = null,
    public readonly ?User $guestBotCallerUser = null,
    public readonly ?Chat $guestBotCallerChat = null,
    public readonly ?int $editDate = null,
    public readonly ?bool $hasProtectedContent = null,
    public readonly ?bool $isFromOffline = null,
    public readonly ?bool $isPaidPost = null,
    public readonly ?string $mediaGroupId = null,
    public readonly ?string $authorSignature = null,
    public readonly ?int $paidStarCount = null,
    public readonly ?string $text = null,
    public readonly ?array $entities = null,
    public readonly ?LinkPreviewOptions $linkPreviewOptions = null,
    public readonly ?SuggestedPostInfo $suggestedPostInfo = null,
    public readonly ?string $effectId = null,
    public readonly ?RichMessage $richMessage = null,
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
    public readonly ?string $caption = null,
    public readonly ?array $captionEntities = null,
    public readonly ?bool $showCaptionAboveMedia = null,
    public readonly ?bool $hasMediaSpoiler = null,
    public readonly ?Checklist $checklist = null,
    public readonly ?Contact $contact = null,
    public readonly ?Dice $dice = null,
    public readonly ?Game $game = null,
    public readonly ?Poll $poll = null,
    public readonly ?Venue $venue = null,
    public readonly ?Location $location = null,
    public readonly ?array $newChatMembers = null,
    public readonly ?User $leftChatMember = null,
    public readonly ?ChatOwnerLeft $chatOwnerLeft = null,
    public readonly ?ChatOwnerChanged $chatOwnerChanged = null,
    public readonly ?string $newChatTitle = null,
    public readonly ?array $newChatPhoto = null,
    public readonly ?bool $deleteChatPhoto = null,
    public readonly ?bool $groupChatCreated = null,
    public readonly ?bool $supergroupChatCreated = null,
    public readonly ?bool $channelChatCreated = null,
    public readonly ?MessageAutoDeleteTimerChanged $messageAutoDeleteTimerChanged = null,
    public readonly ?int $migrateToChatId = null,
    public readonly ?int $migrateFromChatId = null,
    public readonly ?MaybeInaccessibleMessage $pinnedMessage = null,
    public readonly ?Invoice $invoice = null,
    public readonly ?SuccessfulPayment $successfulPayment = null,
    public readonly ?RefundedPayment $refundedPayment = null,
    public readonly ?UsersShared $usersShared = null,
    public readonly ?ChatShared $chatShared = null,
    public readonly ?GiftInfo $gift = null,
    public readonly ?UniqueGiftInfo $uniqueGift = null,
    public readonly ?GiftInfo $giftUpgradeSent = null,
    public readonly ?string $connectedWebsite = null,
    public readonly ?WriteAccessAllowed $writeAccessAllowed = null,
    public readonly ?PassportData $passportData = null,
    public readonly ?ProximityAlertTriggered $proximityAlertTriggered = null,
    public readonly ?ChatBoostAdded $boostAdded = null,
    public readonly ?ChatBackground $chatBackgroundSet = null,
    public readonly ?ChecklistTasksDone $checklistTasksDone = null,
    public readonly ?ChecklistTasksAdded $checklistTasksAdded = null,
    public readonly ?DirectMessagePriceChanged $directMessagePriceChanged = null,
    public readonly ?ForumTopicCreated $forumTopicCreated = null,
    public readonly ?ForumTopicEdited $forumTopicEdited = null,
    public readonly ?ForumTopicClosed $forumTopicClosed = null,
    public readonly ?ForumTopicReopened $forumTopicReopened = null,
    public readonly ?GeneralForumTopicHidden $generalForumTopicHidden = null,
    public readonly ?GeneralForumTopicUnhidden $generalForumTopicUnhidden = null,
    public readonly ?GiveawayCreated $giveawayCreated = null,
    public readonly ?Giveaway $giveaway = null,
    public readonly ?GiveawayWinners $giveawayWinners = null,
    public readonly ?GiveawayCompleted $giveawayCompleted = null,
    public readonly ?ManagedBotCreated $managedBotCreated = null,
    public readonly ?PaidMessagePriceChanged $paidMessagePriceChanged = null,
    public readonly ?PollOptionAdded $pollOptionAdded = null,
    public readonly ?PollOptionDeleted $pollOptionDeleted = null,
    public readonly ?SuggestedPostApproved $suggestedPostApproved = null,
    public readonly ?SuggestedPostApprovalFailed $suggestedPostApprovalFailed = null,
    public readonly ?SuggestedPostDeclined $suggestedPostDeclined = null,
    public readonly ?SuggestedPostPaid $suggestedPostPaid = null,
    public readonly ?SuggestedPostRefunded $suggestedPostRefunded = null,
    public readonly ?VideoChatScheduled $videoChatScheduled = null,
    public readonly ?VideoChatStarted $videoChatStarted = null,
    public readonly ?VideoChatEnded $videoChatEnded = null,
    public readonly ?VideoChatParticipantsInvited $videoChatParticipantsInvited = null,
    public readonly ?WebAppData $webAppData = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }

  /**
   * @param null|list<MessageEntity> $entities
   */
  public function answer(
    string $text,
    ?int $directMessagesTopicId = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $entities = null,
    BotDefault|LinkPreviewOptions $linkPreviewOptions = new BotDefault('link_preview'),
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendMessage {
    return new SendMessage(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      text: $text,
      parseMode: $parseMode,
      entities: $entities,
      linkPreviewOptions: $linkPreviewOptions,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $entities
   */
  public function reply(
    string $text,
    ?int $directMessagesTopicId = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $entities = null,
    BotDefault|LinkPreviewOptions $linkPreviewOptions = new BotDefault('link_preview'),
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendMessage {
    return new SendMessage(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      text: $text,
      parseMode: $parseMode,
      entities: $entities,
      linkPreviewOptions: $linkPreviewOptions,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function answerRich(
    InputRichMessage $richMessage,
    ?int $directMessagesTopicId = null,
    ?bool $disableNotification = null,
    ?bool $protectContent = null,
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendRichMessage {
    return new SendRichMessage(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      richMessage: $richMessage,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function replyRich(
    InputRichMessage $richMessage,
    ?int $directMessagesTopicId = null,
    ?bool $disableNotification = null,
    ?bool $protectContent = null,
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendRichMessage {
    return new SendRichMessage(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      richMessage: $richMessage,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerAnimation(
    InputFile|string $animation,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $width = null,
    ?int $height = null,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendAnimation {
    return new SendAnimation(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      animation: $animation,
      duration: $duration,
      width: $width,
      height: $height,
      thumbnail: $thumbnail,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      hasSpoiler: $hasSpoiler,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function replyAnimation(
    InputFile|string $animation,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $width = null,
    ?int $height = null,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendAnimation {
    return new SendAnimation(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      animation: $animation,
      duration: $duration,
      width: $width,
      height: $height,
      thumbnail: $thumbnail,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      hasSpoiler: $hasSpoiler,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerAudio(
    InputFile|string $audio,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?int $duration = null,
    ?string $performer = null,
    ?string $title = null,
    ?InputFile $thumbnail = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendAudio {
    return new SendAudio(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      audio: $audio,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      duration: $duration,
      performer: $performer,
      title: $title,
      thumbnail: $thumbnail,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function replyAudio(
    InputFile|string $audio,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?int $duration = null,
    ?string $performer = null,
    ?string $title = null,
    ?InputFile $thumbnail = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendAudio {
    return new SendAudio(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      audio: $audio,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      duration: $duration,
      performer: $performer,
      title: $title,
      thumbnail: $thumbnail,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function answerContact(
    string $phoneNumber,
    string $firstName,
    ?int $directMessagesTopicId = null,
    ?string $lastName = null,
    ?string $vcard = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendContact {
    return new SendContact(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      phoneNumber: $phoneNumber,
      firstName: $firstName,
      lastName: $lastName,
      vcard: $vcard,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function replyContact(
    string $phoneNumber,
    string $firstName,
    ?int $directMessagesTopicId = null,
    ?string $lastName = null,
    ?string $vcard = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendContact {
    return new SendContact(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      phoneNumber: $phoneNumber,
      firstName: $firstName,
      lastName: $lastName,
      vcard: $vcard,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerDocument(
    InputFile|string $document,
    ?int $directMessagesTopicId = null,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?bool $disableContentTypeDetection = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendDocument {
    return new SendDocument(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      document: $document,
      thumbnail: $thumbnail,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      disableContentTypeDetection: $disableContentTypeDetection,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function replyDocument(
    InputFile|string $document,
    ?int $directMessagesTopicId = null,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?bool $disableContentTypeDetection = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendDocument {
    return new SendDocument(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      document: $document,
      thumbnail: $thumbnail,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      disableContentTypeDetection: $disableContentTypeDetection,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function answerGame(
    string $gameShortName,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): SendGame {
    return new SendGame(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      gameShortName: $gameShortName,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function replyGame(
    string $gameShortName,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): SendGame {
    return new SendGame(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      gameShortName: $gameShortName,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param list<LabeledPrice> $prices
   * @param null|list<int> $suggestedTipAmounts
   */
  public function answerInvoice(
    string $title,
    string $description,
    string $payload,
    string $currency,
    array $prices,
    ?int $directMessagesTopicId = null,
    ?string $providerToken = null,
    ?int $maxTipAmount = null,
    ?array $suggestedTipAmounts = null,
    ?string $startParameter = null,
    ?string $providerData = null,
    ?string $photoUrl = null,
    ?int $photoSize = null,
    ?int $photoWidth = null,
    ?int $photoHeight = null,
    ?bool $needName = null,
    ?bool $needPhoneNumber = null,
    ?bool $needEmail = null,
    ?bool $needShippingAddress = null,
    ?bool $sendPhoneNumberToProvider = null,
    ?bool $sendEmailToProvider = null,
    ?bool $isFlexible = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): SendInvoice {
    return new SendInvoice(
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      title: $title,
      description: $description,
      payload: $payload,
      providerToken: $providerToken,
      currency: $currency,
      prices: $prices,
      maxTipAmount: $maxTipAmount,
      suggestedTipAmounts: $suggestedTipAmounts,
      startParameter: $startParameter,
      providerData: $providerData,
      photoUrl: $photoUrl,
      photoSize: $photoSize,
      photoWidth: $photoWidth,
      photoHeight: $photoHeight,
      needName: $needName,
      needPhoneNumber: $needPhoneNumber,
      needEmail: $needEmail,
      needShippingAddress: $needShippingAddress,
      sendPhoneNumberToProvider: $sendPhoneNumberToProvider,
      sendEmailToProvider: $sendEmailToProvider,
      isFlexible: $isFlexible,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param list<LabeledPrice> $prices
   * @param null|list<int> $suggestedTipAmounts
   */
  public function replyInvoice(
    string $title,
    string $description,
    string $payload,
    string $currency,
    array $prices,
    ?int $directMessagesTopicId = null,
    ?string $providerToken = null,
    ?int $maxTipAmount = null,
    ?array $suggestedTipAmounts = null,
    ?string $startParameter = null,
    ?string $providerData = null,
    ?string $photoUrl = null,
    ?int $photoSize = null,
    ?int $photoWidth = null,
    ?int $photoHeight = null,
    ?bool $needName = null,
    ?bool $needPhoneNumber = null,
    ?bool $needEmail = null,
    ?bool $needShippingAddress = null,
    ?bool $sendPhoneNumberToProvider = null,
    ?bool $sendEmailToProvider = null,
    ?bool $isFlexible = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): SendInvoice {
    return new SendInvoice(
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      title: $title,
      description: $description,
      payload: $payload,
      providerToken: $providerToken,
      currency: $currency,
      prices: $prices,
      maxTipAmount: $maxTipAmount,
      suggestedTipAmounts: $suggestedTipAmounts,
      startParameter: $startParameter,
      providerData: $providerData,
      photoUrl: $photoUrl,
      photoSize: $photoSize,
      photoWidth: $photoWidth,
      photoHeight: $photoHeight,
      needName: $needName,
      needPhoneNumber: $needPhoneNumber,
      needEmail: $needEmail,
      needShippingAddress: $needShippingAddress,
      sendPhoneNumberToProvider: $sendPhoneNumberToProvider,
      sendEmailToProvider: $sendEmailToProvider,
      isFlexible: $isFlexible,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function answerLocation(
    float $latitude,
    float $longitude,
    ?int $directMessagesTopicId = null,
    ?float $horizontalAccuracy = null,
    ?int $livePeriod = null,
    ?int $heading = null,
    ?int $proximityAlertRadius = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendLocation {
    return new SendLocation(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      latitude: $latitude,
      longitude: $longitude,
      horizontalAccuracy: $horizontalAccuracy,
      livePeriod: $livePeriod,
      heading: $heading,
      proximityAlertRadius: $proximityAlertRadius,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function replyLocation(
    float $latitude,
    float $longitude,
    ?int $directMessagesTopicId = null,
    ?float $horizontalAccuracy = null,
    ?int $livePeriod = null,
    ?int $heading = null,
    ?int $proximityAlertRadius = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendLocation {
    return new SendLocation(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      latitude: $latitude,
      longitude: $longitude,
      horizontalAccuracy: $horizontalAccuracy,
      livePeriod: $livePeriod,
      heading: $heading,
      proximityAlertRadius: $proximityAlertRadius,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param list<InputMediaAudio|InputMediaDocument|InputMediaLivePhoto|InputMediaPhoto|InputMediaVideo> $media
   */
  public function answerMediaGroup(
    array $media,
    ?int $directMessagesTopicId = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
  ): SendMediaGroup {
    return new SendMediaGroup(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      media: $media,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      replyParameters: $replyParameters,
      bot: $this->bot,
    );
  }

  /**
   * @param list<InputMediaAudio|InputMediaDocument|InputMediaLivePhoto|InputMediaPhoto|InputMediaVideo> $media
   */
  public function replyMediaGroup(
    array $media,
    ?int $directMessagesTopicId = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
  ): SendMediaGroup {
    return new SendMediaGroup(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      media: $media,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      replyParameters: $this->asReplyParameters(),
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerPhoto(
    InputFile|string $photo,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendPhoto {
    return new SendPhoto(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      photo: $photo,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      hasSpoiler: $hasSpoiler,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function replyPhoto(
    InputFile|string $photo,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendPhoto {
    return new SendPhoto(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      photo: $photo,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      hasSpoiler: $hasSpoiler,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param list<InputPollOption|string> $options
   * @param null|list<MessageEntity> $questionEntities
   * @param null|list<string> $countryCodes
   * @param null|list<int> $correctOptionIds
   * @param null|list<MessageEntity> $explanationEntities
   * @param null|list<MessageEntity> $descriptionEntities
   */
  public function answerPoll(
    string $question,
    array $options,
    BotDefault|string $questionParseMode = new BotDefault('parse_mode'),
    ?array $questionEntities = null,
    ?bool $isAnonymous = null,
    ?string $type = null,
    ?bool $allowsMultipleAnswers = null,
    ?bool $allowsRevoting = null,
    ?bool $shuffleOptions = null,
    ?bool $allowAddingOptions = null,
    ?bool $hideResultsUntilCloses = null,
    ?bool $membersOnly = null,
    ?array $countryCodes = null,
    ?array $correctOptionIds = null,
    ?string $explanation = null,
    BotDefault|string $explanationParseMode = new BotDefault('parse_mode'),
    ?array $explanationEntities = null,
    ?InputPollMediaInterface $explanationMedia = null,
    ?int $openPeriod = null,
    DateInterval|DateTime|int|null $closeDate = null,
    ?bool $isClosed = null,
    ?string $description = null,
    BotDefault|string $descriptionParseMode = new BotDefault('parse_mode'),
    ?array $descriptionEntities = null,
    ?InputPollMediaInterface $media = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendPoll {
    return new SendPoll(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      question: $question,
      questionParseMode: $questionParseMode,
      questionEntities: $questionEntities,
      options: $options,
      isAnonymous: $isAnonymous,
      type: $type,
      allowsMultipleAnswers: $allowsMultipleAnswers,
      allowsRevoting: $allowsRevoting,
      shuffleOptions: $shuffleOptions,
      allowAddingOptions: $allowAddingOptions,
      hideResultsUntilCloses: $hideResultsUntilCloses,
      membersOnly: $membersOnly,
      countryCodes: $countryCodes,
      correctOptionIds: $correctOptionIds,
      explanation: $explanation,
      explanationParseMode: $explanationParseMode,
      explanationEntities: $explanationEntities,
      explanationMedia: $explanationMedia,
      openPeriod: $openPeriod,
      closeDate: $closeDate,
      isClosed: $isClosed,
      description: $description,
      descriptionParseMode: $descriptionParseMode,
      descriptionEntities: $descriptionEntities,
      media: $media,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param list<InputPollOption|string> $options
   * @param null|list<MessageEntity> $questionEntities
   * @param null|list<string> $countryCodes
   * @param null|list<int> $correctOptionIds
   * @param null|list<MessageEntity> $explanationEntities
   * @param null|list<MessageEntity> $descriptionEntities
   */
  public function replyPoll(
    string $question,
    array $options,
    BotDefault|string $questionParseMode = new BotDefault('parse_mode'),
    ?array $questionEntities = null,
    ?bool $isAnonymous = null,
    ?string $type = null,
    ?bool $allowsMultipleAnswers = null,
    ?bool $allowsRevoting = null,
    ?bool $shuffleOptions = null,
    ?bool $allowAddingOptions = null,
    ?bool $hideResultsUntilCloses = null,
    ?bool $membersOnly = null,
    ?array $countryCodes = null,
    ?array $correctOptionIds = null,
    ?string $explanation = null,
    BotDefault|string $explanationParseMode = new BotDefault('parse_mode'),
    ?array $explanationEntities = null,
    ?InputPollMediaInterface $explanationMedia = null,
    ?int $openPeriod = null,
    DateInterval|DateTime|int|null $closeDate = null,
    ?bool $isClosed = null,
    ?string $description = null,
    BotDefault|string $descriptionParseMode = new BotDefault('parse_mode'),
    ?array $descriptionEntities = null,
    ?InputPollMediaInterface $media = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendPoll {
    return new SendPoll(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      question: $question,
      questionParseMode: $questionParseMode,
      questionEntities: $questionEntities,
      options: $options,
      isAnonymous: $isAnonymous,
      type: $type,
      allowsMultipleAnswers: $allowsMultipleAnswers,
      allowsRevoting: $allowsRevoting,
      shuffleOptions: $shuffleOptions,
      allowAddingOptions: $allowAddingOptions,
      hideResultsUntilCloses: $hideResultsUntilCloses,
      membersOnly: $membersOnly,
      countryCodes: $countryCodes,
      correctOptionIds: $correctOptionIds,
      explanation: $explanation,
      explanationParseMode: $explanationParseMode,
      explanationEntities: $explanationEntities,
      explanationMedia: $explanationMedia,
      openPeriod: $openPeriod,
      closeDate: $closeDate,
      isClosed: $isClosed,
      description: $description,
      descriptionParseMode: $descriptionParseMode,
      descriptionEntities: $descriptionEntities,
      media: $media,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function answerDice(
    ?int $directMessagesTopicId = null,
    ?string $emoji = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendDice {
    return new SendDice(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      emoji: $emoji,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function replyDice(
    ?int $directMessagesTopicId = null,
    ?string $emoji = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendDice {
    return new SendDice(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      emoji: $emoji,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function answerSticker(
    InputFile|string $sticker,
    ?int $directMessagesTopicId = null,
    ?string $emoji = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendSticker {
    return new SendSticker(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      sticker: $sticker,
      emoji: $emoji,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function replySticker(
    InputFile|string $sticker,
    ?int $directMessagesTopicId = null,
    ?string $emoji = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendSticker {
    return new SendSticker(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      sticker: $sticker,
      emoji: $emoji,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function answerVenue(
    float $latitude,
    float $longitude,
    string $title,
    string $address,
    ?int $directMessagesTopicId = null,
    ?string $foursquareId = null,
    ?string $foursquareType = null,
    ?string $googlePlaceId = null,
    ?string $googlePlaceType = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendVenue {
    return new SendVenue(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      latitude: $latitude,
      longitude: $longitude,
      title: $title,
      address: $address,
      foursquareId: $foursquareId,
      foursquareType: $foursquareType,
      googlePlaceId: $googlePlaceId,
      googlePlaceType: $googlePlaceType,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function replyVenue(
    float $latitude,
    float $longitude,
    string $title,
    string $address,
    ?int $directMessagesTopicId = null,
    ?string $foursquareId = null,
    ?string $foursquareType = null,
    ?string $googlePlaceId = null,
    ?string $googlePlaceType = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendVenue {
    return new SendVenue(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      latitude: $latitude,
      longitude: $longitude,
      title: $title,
      address: $address,
      foursquareId: $foursquareId,
      foursquareType: $foursquareType,
      googlePlaceId: $googlePlaceId,
      googlePlaceType: $googlePlaceType,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerVideo(
    InputFile|string $video,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $width = null,
    ?int $height = null,
    ?InputFile $thumbnail = null,
    InputFile|string|null $cover = null,
    DateInterval|DateTime|int|null $startTimestamp = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $supportsStreaming = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendVideo {
    return new SendVideo(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      video: $video,
      duration: $duration,
      width: $width,
      height: $height,
      thumbnail: $thumbnail,
      cover: $cover,
      startTimestamp: $startTimestamp,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      hasSpoiler: $hasSpoiler,
      supportsStreaming: $supportsStreaming,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function replyVideo(
    InputFile|string $video,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $width = null,
    ?int $height = null,
    ?InputFile $thumbnail = null,
    InputFile|string|null $cover = null,
    DateInterval|DateTime|int|null $startTimestamp = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $supportsStreaming = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendVideo {
    return new SendVideo(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      video: $video,
      duration: $duration,
      width: $width,
      height: $height,
      thumbnail: $thumbnail,
      cover: $cover,
      startTimestamp: $startTimestamp,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      hasSpoiler: $hasSpoiler,
      supportsStreaming: $supportsStreaming,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function answerVideoNote(
    InputFile|string $videoNote,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $length = null,
    ?InputFile $thumbnail = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendVideoNote {
    return new SendVideoNote(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      videoNote: $videoNote,
      duration: $duration,
      length: $length,
      thumbnail: $thumbnail,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function replyVideoNote(
    InputFile|string $videoNote,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $length = null,
    ?InputFile $thumbnail = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendVideoNote {
    return new SendVideoNote(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      videoNote: $videoNote,
      duration: $duration,
      length: $length,
      thumbnail: $thumbnail,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerVoice(
    InputFile|string $voice,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?int $duration = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendVoice {
    return new SendVoice(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      voice: $voice,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      duration: $duration,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function replyVoice(
    InputFile|string $voice,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?int $duration = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendVoice {
    return new SendVoice(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      voice: $voice,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      duration: $duration,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param list<InputPaidMedia> $media
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerPaidMedia(
    int $starCount,
    array $media,
    ?int $directMessagesTopicId = null,
    ?string $payload = null,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?bool $showCaptionAboveMedia = null,
    ?bool $disableNotification = null,
    ?bool $protectContent = null,
    ?bool $allowPaidBroadcast = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendPaidMedia {
    return new SendPaidMedia(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      starCount: $starCount,
      media: $media,
      payload: $payload,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param list<InputPaidMedia> $media
   * @param null|list<MessageEntity> $captionEntities
   */
  public function replyPaidMedia(
    int $starCount,
    array $media,
    ?int $directMessagesTopicId = null,
    ?string $payload = null,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?bool $showCaptionAboveMedia = null,
    ?bool $disableNotification = null,
    ?bool $protectContent = null,
    ?bool $allowPaidBroadcast = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): SendPaidMedia {
    return new SendPaidMedia(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $this->isTopicMessage ? $this->messageThreadId : null,
      directMessagesTopicId: $directMessagesTopicId,
      starCount: $starCount,
      media: $media,
      payload: $payload,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $this->asReplyParameters(),
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function copyTo(
    int|string $chatId,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    DateInterval|DateTime|int|null $videoStartTimestamp = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
  ): CopyMessage {
    return new CopyMessage(
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
      fromChatId: $this->chat->id,
      messageId: $this->messageId,
      videoStartTimestamp: $videoStartTimestamp,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function forward(
    int|string $chatId,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    DateInterval|DateTime|int|null $videoStartTimestamp = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
  ): ForwardMessage {
    return new ForwardMessage(
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
      fromChatId: $this->chat->id,
      videoStartTimestamp: $videoStartTimestamp,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      messageId: $this->messageId,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $entities
   */
  public function editText(
    ?string $text = null,
    ?string $inlineMessageId = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $entities = null,
    BotDefault|LinkPreviewOptions $linkPreviewOptions = new BotDefault('link_preview'),
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?InputRichMessage $richMessage = null,
  ): EditMessageText {
    return new EditMessageText(
      text: $text,
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      inlineMessageId: $inlineMessageId,
      parseMode: $parseMode,
      entities: $entities,
      linkPreviewOptions: $linkPreviewOptions,
      replyMarkup: $replyMarkup,
      richMessage: $richMessage,
      bot: $this->bot,
    );
  }

  public function editMedia(
    InputMedia $media,
    ?string $inlineMessageId = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): EditMessageMedia {
    return new EditMessageMedia(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      inlineMessageId: $inlineMessageId,
      media: $media,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function editReplyMarkup(
    ?string $inlineMessageId = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): EditMessageReplyMarkup {
    return new EditMessageReplyMarkup(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      inlineMessageId: $inlineMessageId,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function deleteReplyMarkup(
    ?string $inlineMessageId = null,
  ): EditMessageReplyMarkup {
    return new EditMessageReplyMarkup(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      inlineMessageId: $inlineMessageId,
      replyMarkup: null,
      bot: $this->bot,
    );
  }

  public function editLiveLocation(
    float $latitude,
    float $longitude,
    ?string $inlineMessageId = null,
    ?int $livePeriod = null,
    ?float $horizontalAccuracy = null,
    ?int $heading = null,
    ?int $proximityAlertRadius = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): EditMessageLiveLocation {
    return new EditMessageLiveLocation(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      inlineMessageId: $inlineMessageId,
      latitude: $latitude,
      longitude: $longitude,
      livePeriod: $livePeriod,
      horizontalAccuracy: $horizontalAccuracy,
      heading: $heading,
      proximityAlertRadius: $proximityAlertRadius,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function stopLiveLocation(
    ?string $inlineMessageId = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): StopMessageLiveLocation {
    return new StopMessageLiveLocation(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      inlineMessageId: $inlineMessageId,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function editCaption(
    ?string $inlineMessageId = null,
    ?string $caption = null,
    BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): EditMessageCaption {
    return new EditMessageCaption(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      inlineMessageId: $inlineMessageId,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      replyMarkup: $replyMarkup,
      bot: $this->bot,
    );
  }

  public function delete(
  ): DeleteMessage {
    return new DeleteMessage(
      chatId: $this->chat->id,
      messageId: $this->messageId,
      bot: $this->bot,
    );
  }

  public function pin(
    ?bool $disableNotification = null,
  ): PinChatMessage {
    return new PinChatMessage(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      disableNotification: $disableNotification,
      bot: $this->bot,
    );
  }

  public function unpin(
  ): UnpinChatMessage {
    return new UnpinChatMessage(
      businessConnectionId: $this->businessConnectionId,
      chatId: $this->chat->id,
      messageId: $this->messageId,
      bot: $this->bot,
    );
  }

  /**
   * @param null|list<ReactionType> $reaction
   */
  public function react(
    ?array $reaction = null,
    ?bool $isBig = null,
  ): SetMessageReaction {
    return new SetMessageReaction(
      chatId: $this->chat->id,
      messageId: $this->messageId,
      reaction: $reaction,
      isBig: $isBig,
      bot: $this->bot,
    );
  }

  public function answerGuestQuery(
    InlineQueryResult $result,
  ): AnswerGuestQuery {
    return new AnswerGuestQuery(
      guestQueryId: $this->guestQueryId ?? throw new LogicException('Shortcut Message::answerGuestQuery requires \'guest_query_id\' to be set on this Message.'),
      result: $result,
      bot: $this->bot,
    );
  }
}
