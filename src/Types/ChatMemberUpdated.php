<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
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
use Gruven\PhpBotGram\Methods\SendPhoto;
use Gruven\PhpBotGram\Methods\SendPoll;
use Gruven\PhpBotGram\Methods\SendSticker;
use Gruven\PhpBotGram\Methods\SendVenue;
use Gruven\PhpBotGram\Methods\SendVideo;
use Gruven\PhpBotGram\Methods\SendVideoNote;
use Gruven\PhpBotGram\Methods\SendVoice;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents changes in the status of a chat member.
 *
 * Source: https://core.telegram.org/bots/api#chatmemberupdated
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatMemberUpdated extends TelegramObject
{
  /** @var array<string, string> */
  public const array WireNames = [
    'fromUser' => 'from',
  ];

  public function __construct(
    public readonly Chat $chat,
    public readonly User $fromUser,
    public readonly DateTime $date,
    public readonly ChatMember $oldChatMember,
    public readonly ChatMember $newChatMember,
    public readonly ?ChatInviteLink $inviteLink = null,
    public readonly ?bool $viaJoinRequest = null,
    public readonly ?bool $viaChatFolderInviteLink = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }

  /**
   * @param null|list<MessageEntity> $entities
   */
  public function answer(
    string $text,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerAnimation(
    InputFile|string $animation,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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
  public function answerAudio(
    InputFile|string $audio,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  public function answerContact(
    string $phoneNumber,
    string $firstName,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerDocument(
    InputFile|string $document,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  public function answerGame(
    string $gameShortName,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
  ): SendGame {
    return new SendGame(
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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
    ?int $messageThreadId = null,
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
      messageThreadId: $messageThreadId,
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

  public function answerLocation(
    float $latitude,
    float $longitude,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  /**
   * @param list<InputMediaAudio|InputMediaDocument|InputMediaLivePhoto|InputMediaPhoto|InputMediaVideo> $media
   */
  public function answerMediaGroup(
    array $media,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?bool $disableNotification = null,
    bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
  ): SendMediaGroup {
    return new SendMediaGroup(
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerPhoto(
    InputFile|string $photo,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  public function answerDice(
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  public function answerSticker(
    InputFile|string $sticker,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  public function answerVenue(
    float $latitude,
    float $longitude,
    string $title,
    string $address,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerVideo(
    InputFile|string $video,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  public function answerVideoNote(
    InputFile|string $videoNote,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function answerVoice(
    InputFile|string $voice,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
      businessConnectionId: $businessConnectionId,
      chatId: $this->chat->id,
      messageThreadId: $messageThreadId,
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
}
