<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram;

use DateInterval;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Client\BotShortcuts;
use Gruven\PhpBotGram\Client\BotShortcutsContract;
use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Client\Session\BaseSession;
use Gruven\PhpBotGram\Methods\AddStickerToSet;
use Gruven\PhpBotGram\Methods\AnswerCallbackQuery;
use Gruven\PhpBotGram\Methods\AnswerGuestQuery;
use Gruven\PhpBotGram\Methods\AnswerInlineQuery;
use Gruven\PhpBotGram\Methods\AnswerPreCheckoutQuery;
use Gruven\PhpBotGram\Methods\AnswerShippingQuery;
use Gruven\PhpBotGram\Methods\AnswerWebAppQuery;
use Gruven\PhpBotGram\Methods\ApproveChatJoinRequest;
use Gruven\PhpBotGram\Methods\ApproveSuggestedPost;
use Gruven\PhpBotGram\Methods\BanChatMember;
use Gruven\PhpBotGram\Methods\BanChatSenderChat;
use Gruven\PhpBotGram\Methods\Close;
use Gruven\PhpBotGram\Methods\CloseForumTopic;
use Gruven\PhpBotGram\Methods\CloseGeneralForumTopic;
use Gruven\PhpBotGram\Methods\ConvertGiftToStars;
use Gruven\PhpBotGram\Methods\CopyMessage;
use Gruven\PhpBotGram\Methods\CopyMessages;
use Gruven\PhpBotGram\Methods\CreateChatInviteLink;
use Gruven\PhpBotGram\Methods\CreateChatSubscriptionInviteLink;
use Gruven\PhpBotGram\Methods\CreateForumTopic;
use Gruven\PhpBotGram\Methods\CreateInvoiceLink;
use Gruven\PhpBotGram\Methods\CreateNewStickerSet;
use Gruven\PhpBotGram\Methods\DeclineChatJoinRequest;
use Gruven\PhpBotGram\Methods\DeclineSuggestedPost;
use Gruven\PhpBotGram\Methods\DeleteAllMessageReactions;
use Gruven\PhpBotGram\Methods\DeleteBusinessMessages;
use Gruven\PhpBotGram\Methods\DeleteChatPhoto;
use Gruven\PhpBotGram\Methods\DeleteChatStickerSet;
use Gruven\PhpBotGram\Methods\DeleteForumTopic;
use Gruven\PhpBotGram\Methods\DeleteMessage;
use Gruven\PhpBotGram\Methods\DeleteMessageReaction;
use Gruven\PhpBotGram\Methods\DeleteMessages;
use Gruven\PhpBotGram\Methods\DeleteMyCommands;
use Gruven\PhpBotGram\Methods\DeleteStickerFromSet;
use Gruven\PhpBotGram\Methods\DeleteStickerSet;
use Gruven\PhpBotGram\Methods\DeleteStory;
use Gruven\PhpBotGram\Methods\DeleteWebhook;
use Gruven\PhpBotGram\Methods\EditChatInviteLink;
use Gruven\PhpBotGram\Methods\EditChatSubscriptionInviteLink;
use Gruven\PhpBotGram\Methods\EditForumTopic;
use Gruven\PhpBotGram\Methods\EditGeneralForumTopic;
use Gruven\PhpBotGram\Methods\EditMessageCaption;
use Gruven\PhpBotGram\Methods\EditMessageChecklist;
use Gruven\PhpBotGram\Methods\EditMessageLiveLocation;
use Gruven\PhpBotGram\Methods\EditMessageMedia;
use Gruven\PhpBotGram\Methods\EditMessageReplyMarkup;
use Gruven\PhpBotGram\Methods\EditMessageText;
use Gruven\PhpBotGram\Methods\EditStory;
use Gruven\PhpBotGram\Methods\EditUserStarSubscription;
use Gruven\PhpBotGram\Methods\ExportChatInviteLink;
use Gruven\PhpBotGram\Methods\ForwardMessage;
use Gruven\PhpBotGram\Methods\ForwardMessages;
use Gruven\PhpBotGram\Methods\GetAvailableGifts;
use Gruven\PhpBotGram\Methods\GetBusinessAccountGifts;
use Gruven\PhpBotGram\Methods\GetBusinessAccountStarBalance;
use Gruven\PhpBotGram\Methods\GetBusinessConnection;
use Gruven\PhpBotGram\Methods\GetChat;
use Gruven\PhpBotGram\Methods\GetChatAdministrators;
use Gruven\PhpBotGram\Methods\GetChatGifts;
use Gruven\PhpBotGram\Methods\GetChatMember;
use Gruven\PhpBotGram\Methods\GetChatMemberCount;
use Gruven\PhpBotGram\Methods\GetChatMenuButton;
use Gruven\PhpBotGram\Methods\GetCustomEmojiStickers;
use Gruven\PhpBotGram\Methods\GetFile;
use Gruven\PhpBotGram\Methods\GetForumTopicIconStickers;
use Gruven\PhpBotGram\Methods\GetGameHighScores;
use Gruven\PhpBotGram\Methods\GetManagedBotAccessSettings;
use Gruven\PhpBotGram\Methods\GetManagedBotToken;
use Gruven\PhpBotGram\Methods\GetMe;
use Gruven\PhpBotGram\Methods\GetMyCommands;
use Gruven\PhpBotGram\Methods\GetMyDefaultAdministratorRights;
use Gruven\PhpBotGram\Methods\GetMyDescription;
use Gruven\PhpBotGram\Methods\GetMyName;
use Gruven\PhpBotGram\Methods\GetMyShortDescription;
use Gruven\PhpBotGram\Methods\GetMyStarBalance;
use Gruven\PhpBotGram\Methods\GetStarTransactions;
use Gruven\PhpBotGram\Methods\GetStickerSet;
use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Methods\GetUserChatBoosts;
use Gruven\PhpBotGram\Methods\GetUserGifts;
use Gruven\PhpBotGram\Methods\GetUserPersonalChatMessages;
use Gruven\PhpBotGram\Methods\GetUserProfileAudios;
use Gruven\PhpBotGram\Methods\GetUserProfilePhotos;
use Gruven\PhpBotGram\Methods\GetWebhookInfo;
use Gruven\PhpBotGram\Methods\GiftPremiumSubscription;
use Gruven\PhpBotGram\Methods\HideGeneralForumTopic;
use Gruven\PhpBotGram\Methods\LeaveChat;
use Gruven\PhpBotGram\Methods\LogOut;
use Gruven\PhpBotGram\Methods\PinChatMessage;
use Gruven\PhpBotGram\Methods\PostStory;
use Gruven\PhpBotGram\Methods\PromoteChatMember;
use Gruven\PhpBotGram\Methods\ReadBusinessMessage;
use Gruven\PhpBotGram\Methods\RefundStarPayment;
use Gruven\PhpBotGram\Methods\RemoveBusinessAccountProfilePhoto;
use Gruven\PhpBotGram\Methods\RemoveChatVerification;
use Gruven\PhpBotGram\Methods\RemoveMyProfilePhoto;
use Gruven\PhpBotGram\Methods\RemoveUserVerification;
use Gruven\PhpBotGram\Methods\ReopenForumTopic;
use Gruven\PhpBotGram\Methods\ReopenGeneralForumTopic;
use Gruven\PhpBotGram\Methods\ReplaceManagedBotToken;
use Gruven\PhpBotGram\Methods\ReplaceStickerInSet;
use Gruven\PhpBotGram\Methods\RepostStory;
use Gruven\PhpBotGram\Methods\RestrictChatMember;
use Gruven\PhpBotGram\Methods\RevokeChatInviteLink;
use Gruven\PhpBotGram\Methods\SavePreparedInlineMessage;
use Gruven\PhpBotGram\Methods\SavePreparedKeyboardButton;
use Gruven\PhpBotGram\Methods\SendAnimation;
use Gruven\PhpBotGram\Methods\SendAudio;
use Gruven\PhpBotGram\Methods\SendChatAction;
use Gruven\PhpBotGram\Methods\SendChecklist;
use Gruven\PhpBotGram\Methods\SendContact;
use Gruven\PhpBotGram\Methods\SendDice;
use Gruven\PhpBotGram\Methods\SendDocument;
use Gruven\PhpBotGram\Methods\SendGame;
use Gruven\PhpBotGram\Methods\SendGift;
use Gruven\PhpBotGram\Methods\SendInvoice;
use Gruven\PhpBotGram\Methods\SendLivePhoto;
use Gruven\PhpBotGram\Methods\SendLocation;
use Gruven\PhpBotGram\Methods\SendMediaGroup;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Methods\SendMessageDraft;
use Gruven\PhpBotGram\Methods\SendPaidMedia;
use Gruven\PhpBotGram\Methods\SendPhoto;
use Gruven\PhpBotGram\Methods\SendPoll;
use Gruven\PhpBotGram\Methods\SendSticker;
use Gruven\PhpBotGram\Methods\SendVenue;
use Gruven\PhpBotGram\Methods\SendVideo;
use Gruven\PhpBotGram\Methods\SendVideoNote;
use Gruven\PhpBotGram\Methods\SendVoice;
use Gruven\PhpBotGram\Methods\SetBusinessAccountBio;
use Gruven\PhpBotGram\Methods\SetBusinessAccountGiftSettings;
use Gruven\PhpBotGram\Methods\SetBusinessAccountName;
use Gruven\PhpBotGram\Methods\SetBusinessAccountProfilePhoto;
use Gruven\PhpBotGram\Methods\SetBusinessAccountUsername;
use Gruven\PhpBotGram\Methods\SetChatAdministratorCustomTitle;
use Gruven\PhpBotGram\Methods\SetChatDescription;
use Gruven\PhpBotGram\Methods\SetChatMemberTag;
use Gruven\PhpBotGram\Methods\SetChatMenuButton;
use Gruven\PhpBotGram\Methods\SetChatPermissions;
use Gruven\PhpBotGram\Methods\SetChatPhoto;
use Gruven\PhpBotGram\Methods\SetChatStickerSet;
use Gruven\PhpBotGram\Methods\SetChatTitle;
use Gruven\PhpBotGram\Methods\SetCustomEmojiStickerSetThumbnail;
use Gruven\PhpBotGram\Methods\SetGameScore;
use Gruven\PhpBotGram\Methods\SetManagedBotAccessSettings;
use Gruven\PhpBotGram\Methods\SetMessageReaction;
use Gruven\PhpBotGram\Methods\SetMyCommands;
use Gruven\PhpBotGram\Methods\SetMyDefaultAdministratorRights;
use Gruven\PhpBotGram\Methods\SetMyDescription;
use Gruven\PhpBotGram\Methods\SetMyName;
use Gruven\PhpBotGram\Methods\SetMyProfilePhoto;
use Gruven\PhpBotGram\Methods\SetMyShortDescription;
use Gruven\PhpBotGram\Methods\SetPassportDataErrors;
use Gruven\PhpBotGram\Methods\SetStickerEmojiList;
use Gruven\PhpBotGram\Methods\SetStickerKeywords;
use Gruven\PhpBotGram\Methods\SetStickerMaskPosition;
use Gruven\PhpBotGram\Methods\SetStickerPositionInSet;
use Gruven\PhpBotGram\Methods\SetStickerSetThumbnail;
use Gruven\PhpBotGram\Methods\SetStickerSetTitle;
use Gruven\PhpBotGram\Methods\SetUserEmojiStatus;
use Gruven\PhpBotGram\Methods\SetWebhook;
use Gruven\PhpBotGram\Methods\StopMessageLiveLocation;
use Gruven\PhpBotGram\Methods\StopPoll;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Methods\TransferBusinessAccountStars;
use Gruven\PhpBotGram\Methods\TransferGift;
use Gruven\PhpBotGram\Methods\UnbanChatMember;
use Gruven\PhpBotGram\Methods\UnbanChatSenderChat;
use Gruven\PhpBotGram\Methods\UnhideGeneralForumTopic;
use Gruven\PhpBotGram\Methods\UnpinAllChatMessages;
use Gruven\PhpBotGram\Methods\UnpinAllForumTopicMessages;
use Gruven\PhpBotGram\Methods\UnpinAllGeneralForumTopicMessages;
use Gruven\PhpBotGram\Methods\UnpinChatMessage;
use Gruven\PhpBotGram\Methods\UpgradeGift;
use Gruven\PhpBotGram\Methods\UploadStickerFile;
use Gruven\PhpBotGram\Methods\VerifyChat;
use Gruven\PhpBotGram\Methods\VerifyUser;
use Gruven\PhpBotGram\Types\AcceptedGiftTypes;
use Gruven\PhpBotGram\Types\BotAccessSettings;
use Gruven\PhpBotGram\Types\BotCommand;
use Gruven\PhpBotGram\Types\BotCommandScope;
use Gruven\PhpBotGram\Types\BotDescription;
use Gruven\PhpBotGram\Types\BotName;
use Gruven\PhpBotGram\Types\BotShortDescription;
use Gruven\PhpBotGram\Types\BusinessConnection;
use Gruven\PhpBotGram\Types\ChatAdministratorRights;
use Gruven\PhpBotGram\Types\ChatFullInfo;
use Gruven\PhpBotGram\Types\ChatInviteLink;
use Gruven\PhpBotGram\Types\ChatMember;
use Gruven\PhpBotGram\Types\ChatPermissions;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\File;
use Gruven\PhpBotGram\Types\ForceReply;
use Gruven\PhpBotGram\Types\ForumTopic;
use Gruven\PhpBotGram\Types\GameHighScore;
use Gruven\PhpBotGram\Types\Gifts;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\InlineQueryResult;
use Gruven\PhpBotGram\Types\InlineQueryResultsButton;
use Gruven\PhpBotGram\Types\InputChecklist;
use Gruven\PhpBotGram\Types\InputFile;
use Gruven\PhpBotGram\Types\InputMedia;
use Gruven\PhpBotGram\Types\InputMediaAudio;
use Gruven\PhpBotGram\Types\InputMediaDocument;
use Gruven\PhpBotGram\Types\InputMediaLivePhoto;
use Gruven\PhpBotGram\Types\InputMediaPhoto;
use Gruven\PhpBotGram\Types\InputMediaVideo;
use Gruven\PhpBotGram\Types\InputPaidMedia;
use Gruven\PhpBotGram\Types\InputPollMedia;
use Gruven\PhpBotGram\Types\InputPollOption;
use Gruven\PhpBotGram\Types\InputProfilePhoto;
use Gruven\PhpBotGram\Types\InputSticker;
use Gruven\PhpBotGram\Types\InputStoryContent;
use Gruven\PhpBotGram\Types\KeyboardButton;
use Gruven\PhpBotGram\Types\LabeledPrice;
use Gruven\PhpBotGram\Types\LinkPreviewOptions;
use Gruven\PhpBotGram\Types\MaskPosition;
use Gruven\PhpBotGram\Types\MenuButton;
use Gruven\PhpBotGram\Types\MenuButtonCommands;
use Gruven\PhpBotGram\Types\MenuButtonDefault;
use Gruven\PhpBotGram\Types\MenuButtonWebApp;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\MessageId;
use Gruven\PhpBotGram\Types\OwnedGifts;
use Gruven\PhpBotGram\Types\PassportElementError;
use Gruven\PhpBotGram\Types\Poll;
use Gruven\PhpBotGram\Types\PreparedInlineMessage;
use Gruven\PhpBotGram\Types\PreparedKeyboardButton;
use Gruven\PhpBotGram\Types\ReactionType;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use Gruven\PhpBotGram\Types\ReplyKeyboardRemove;
use Gruven\PhpBotGram\Types\ReplyParameters;
use Gruven\PhpBotGram\Types\SentGuestMessage;
use Gruven\PhpBotGram\Types\SentWebAppMessage;
use Gruven\PhpBotGram\Types\ShippingOption;
use Gruven\PhpBotGram\Types\StarAmount;
use Gruven\PhpBotGram\Types\StarTransactions;
use Gruven\PhpBotGram\Types\Sticker;
use Gruven\PhpBotGram\Types\StickerSet;
use Gruven\PhpBotGram\Types\Story;
use Gruven\PhpBotGram\Types\StoryArea;
use Gruven\PhpBotGram\Types\SuggestedPostParameters;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Types\UserChatBoosts;
use Gruven\PhpBotGram\Types\UserProfileAudios;
use Gruven\PhpBotGram\Types\UserProfilePhotos;
use Gruven\PhpBotGram\Types\WebhookInfo;
use Gruven\PhpBotGram\Utils\Token;

/**
 * Bot facade.
 *
 * Provides one wrapper per Telegram API method, plus the hand-coded
 * constructor / `__invoke` dispatch entry-point preserved from Phase 1.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
class Bot implements BotShortcutsContract
{
  use BotShortcuts;

  public readonly BaseSession $session;
  public readonly DefaultBotProperties $defaultProperties;

  public function __construct(
    public readonly string $token,
    ?BaseSession $session = null,
    ?DefaultBotProperties $defaultProperties = null,
  ) {
    Token::validate($token);
    $this->session = $session ?? new AmphpSession();
    $this->defaultProperties = $defaultProperties ?? new DefaultBotProperties();
  }

  public function getDefaultProperties(): DefaultBotProperties
  {
    return $this->defaultProperties;
  }

  /**
   * Polymorphic entry point: $bot($method) dispatches the method through the
   * session's middleware chain.
   *
   * @template TReturn
   *
   * @param TelegramMethod<TReturn> $method
   *
   * @return TReturn
   */
  public function __invoke(TelegramMethod $method, ?int $timeout = null): mixed
  {
    return ($this->session)($this, $method, $timeout);
  }

  /**
   * Note: $timeout is the long-poll timeout (seconds) carried on the wire to Telegram; $apiTimeout is the HTTP transport timeout for the underlying request.
   *
   * @param null|list<string> $allowedUpdates
   *
   * @return list<Update>
   */
  public function getUpdates(
    ?int $offset = null,
    ?int $limit = null,
    ?int $timeout = null,
    ?array $allowedUpdates = null,
    ?int $apiTimeout = null,
  ): array {
    /** @var list<Update> */
    return $this(new GetUpdates(
      offset: $offset,
      limit: $limit,
      timeout: $timeout,
      allowedUpdates: $allowedUpdates,
    ), $apiTimeout);
  }

  /**
   * @param null|list<string> $allowedUpdates
   */
  public function setWebhook(
    string $url,
    ?InputFile $certificate = null,
    ?string $ipAddress = null,
    ?int $maxConnections = null,
    ?array $allowedUpdates = null,
    ?bool $dropPendingUpdates = null,
    ?string $secretToken = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetWebhook(
      url: $url,
      certificate: $certificate,
      ipAddress: $ipAddress,
      maxConnections: $maxConnections,
      allowedUpdates: $allowedUpdates,
      dropPendingUpdates: $dropPendingUpdates,
      secretToken: $secretToken,
    ), $timeout);
  }
  public function deleteWebhook(
    ?bool $dropPendingUpdates = null,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteWebhook(
      dropPendingUpdates: $dropPendingUpdates,
    ), $timeout);
  }
  public function getWebhookInfo(
    ?int $timeout = null,
  ): WebhookInfo {
    return $this(new GetWebhookInfo(
    ), $timeout);
  }
  public function getMe(
    ?int $timeout = null,
  ): User {
    return $this(new GetMe(
    ), $timeout);
  }
  public function logOut(
    ?int $timeout = null,
  ): bool {
    return $this(new LogOut(
    ), $timeout);
  }
  public function close(
    ?int $timeout = null,
  ): bool {
    return $this(new Close(
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $entities
   */
  public function sendMessage(
    int|string $chatId,
    string $text,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $entities = null,
    null|BotDefault|LinkPreviewOptions $linkPreviewOptions = new BotDefault('link_preview'),
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendMessage(
      chatId: $chatId,
      text: $text,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }
  public function forwardMessage(
    int|string $chatId,
    int|string $fromChatId,
    int $messageId,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    null|DateInterval|DateTime|int $videoStartTimestamp = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?int $timeout = null,
  ): Message {
    return $this(new ForwardMessage(
      chatId: $chatId,
      fromChatId: $fromChatId,
      messageId: $messageId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
      videoStartTimestamp: $videoStartTimestamp,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
    ), $timeout);
  }

  /**
   * @param list<int> $messageIds
   *
   * @return list<MessageId>
   */
  public function forwardMessages(
    int|string $chatId,
    int|string $fromChatId,
    array $messageIds,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?bool $disableNotification = null,
    ?bool $protectContent = null,
    ?int $timeout = null,
  ): array {
    /** @var list<MessageId> */
    return $this(new ForwardMessages(
      chatId: $chatId,
      fromChatId: $fromChatId,
      messageIds: $messageIds,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function copyMessage(
    int|string $chatId,
    int|string $fromChatId,
    int $messageId,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    null|DateInterval|DateTime|int $videoStartTimestamp = null,
    ?string $caption = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): MessageId {
    return $this(new CopyMessage(
      chatId: $chatId,
      fromChatId: $fromChatId,
      messageId: $messageId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param list<int> $messageIds
   *
   * @return list<MessageId>
   */
  public function copyMessages(
    int|string $chatId,
    int|string $fromChatId,
    array $messageIds,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?bool $disableNotification = null,
    ?bool $protectContent = null,
    ?bool $removeCaption = null,
    ?int $timeout = null,
  ): array {
    /** @var list<MessageId> */
    return $this(new CopyMessages(
      chatId: $chatId,
      fromChatId: $fromChatId,
      messageIds: $messageIds,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      removeCaption: $removeCaption,
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function sendPhoto(
    int|string $chatId,
    InputFile|string $photo,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendPhoto(
      chatId: $chatId,
      photo: $photo,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function sendLivePhoto(
    int|string $chatId,
    InputFile|string $livePhoto,
    InputFile|string $photo,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?bool $showCaptionAboveMedia = null,
    ?bool $hasSpoiler = null,
    ?bool $disableNotification = null,
    ?bool $protectContent = null,
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendLivePhoto(
      chatId: $chatId,
      livePhoto: $livePhoto,
      photo: $photo,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function sendAudio(
    int|string $chatId,
    InputFile|string $audio,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?int $duration = null,
    ?string $performer = null,
    ?string $title = null,
    ?InputFile $thumbnail = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendAudio(
      chatId: $chatId,
      audio: $audio,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function sendDocument(
    int|string $chatId,
    InputFile|string $document,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?bool $disableContentTypeDetection = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendDocument(
      chatId: $chatId,
      document: $document,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function sendVideo(
    int|string $chatId,
    InputFile|string $video,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $width = null,
    ?int $height = null,
    ?InputFile $thumbnail = null,
    null|InputFile|string $cover = null,
    null|DateInterval|DateTime|int $startTimestamp = null,
    ?string $caption = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $supportsStreaming = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendVideo(
      chatId: $chatId,
      video: $video,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function sendAnimation(
    int|string $chatId,
    InputFile|string $animation,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $width = null,
    ?int $height = null,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?bool $hasSpoiler = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendAnimation(
      chatId: $chatId,
      animation: $animation,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function sendVoice(
    int|string $chatId,
    InputFile|string $voice,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?string $caption = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    ?int $duration = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendVoice(
      chatId: $chatId,
      voice: $voice,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }
  public function sendVideoNote(
    int|string $chatId,
    InputFile|string $videoNote,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?int $duration = null,
    ?int $length = null,
    ?InputFile $thumbnail = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendVideoNote(
      chatId: $chatId,
      videoNote: $videoNote,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param list<InputPaidMedia> $media
   * @param null|list<MessageEntity> $captionEntities
   */
  public function sendPaidMedia(
    int|string $chatId,
    int $starCount,
    array $media,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
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
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendPaidMedia(
      chatId: $chatId,
      starCount: $starCount,
      media: $media,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }

  /**
   * @param list<InputMediaAudio|InputMediaDocument|InputMediaLivePhoto|InputMediaPhoto|InputMediaVideo> $media
   *
   * @return list<Message>
   */
  public function sendMediaGroup(
    int|string $chatId,
    array $media,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
    ?int $timeout = null,
  ): array {
    /** @var list<Message> */
    return $this(new SendMediaGroup(
      chatId: $chatId,
      media: $media,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      replyParameters: $replyParameters,
    ), $timeout);
  }
  public function sendLocation(
    int|string $chatId,
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
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendLocation(
      chatId: $chatId,
      latitude: $latitude,
      longitude: $longitude,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }
  public function sendVenue(
    int|string $chatId,
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
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendVenue(
      chatId: $chatId,
      latitude: $latitude,
      longitude: $longitude,
      title: $title,
      address: $address,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
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
    ), $timeout);
  }
  public function sendContact(
    int|string $chatId,
    string $phoneNumber,
    string $firstName,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?string $lastName = null,
    ?string $vcard = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendContact(
      chatId: $chatId,
      phoneNumber: $phoneNumber,
      firstName: $firstName,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
      lastName: $lastName,
      vcard: $vcard,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      suggestedPostParameters: $suggestedPostParameters,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }

  /**
   * @param list<InputPollOption|string> $options
   * @param null|list<MessageEntity> $questionEntities
   * @param null|list<string> $countryCodes
   * @param null|list<int> $correctOptionIds
   * @param null|list<MessageEntity> $explanationEntities
   * @param null|list<MessageEntity> $descriptionEntities
   */
  public function sendPoll(
    int|string $chatId,
    string $question,
    array $options,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    null|BotDefault|string $questionParseMode = new BotDefault('parse_mode'),
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
    null|BotDefault|string $explanationParseMode = new BotDefault('parse_mode'),
    ?array $explanationEntities = null,
    ?InputPollMedia $explanationMedia = null,
    ?int $openPeriod = null,
    null|DateInterval|DateTime|int $closeDate = null,
    ?bool $isClosed = null,
    ?string $description = null,
    null|BotDefault|string $descriptionParseMode = new BotDefault('parse_mode'),
    ?array $descriptionEntities = null,
    ?InputPollMedia $media = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendPoll(
      chatId: $chatId,
      question: $question,
      options: $options,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      questionParseMode: $questionParseMode,
      questionEntities: $questionEntities,
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
    ), $timeout);
  }
  public function sendChecklist(
    string $businessConnectionId,
    int|string $chatId,
    InputChecklist $checklist,
    ?bool $disableNotification = null,
    ?bool $protectContent = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendChecklist(
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      checklist: $checklist,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      messageEffectId: $messageEffectId,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function sendDice(
    int|string $chatId,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?string $emoji = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendDice(
      chatId: $chatId,
      businessConnectionId: $businessConnectionId,
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
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $entities
   */
  public function sendMessageDraft(
    int $chatId,
    int $draftId,
    ?int $messageThreadId = null,
    ?string $text = null,
    ?string $parseMode = null,
    ?array $entities = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SendMessageDraft(
      chatId: $chatId,
      draftId: $draftId,
      messageThreadId: $messageThreadId,
      text: $text,
      parseMode: $parseMode,
      entities: $entities,
    ), $timeout);
  }
  public function sendChatAction(
    int|string $chatId,
    string $action,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SendChatAction(
      chatId: $chatId,
      action: $action,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
    ), $timeout);
  }

  /**
   * @param null|list<ReactionType> $reaction
   */
  public function setMessageReaction(
    int|string $chatId,
    int $messageId,
    ?array $reaction = null,
    ?bool $isBig = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetMessageReaction(
      chatId: $chatId,
      messageId: $messageId,
      reaction: $reaction,
      isBig: $isBig,
    ), $timeout);
  }
  public function getUserProfilePhotos(
    int $userId,
    ?int $offset = null,
    ?int $limit = null,
    ?int $timeout = null,
  ): UserProfilePhotos {
    return $this(new GetUserProfilePhotos(
      userId: $userId,
      offset: $offset,
      limit: $limit,
    ), $timeout);
  }
  public function getUserProfileAudios(
    int $userId,
    ?int $offset = null,
    ?int $limit = null,
    ?int $timeout = null,
  ): UserProfileAudios {
    return $this(new GetUserProfileAudios(
      userId: $userId,
      offset: $offset,
      limit: $limit,
    ), $timeout);
  }
  public function setUserEmojiStatus(
    int $userId,
    ?string $emojiStatusCustomEmojiId = null,
    null|DateInterval|DateTime|int $emojiStatusExpirationDate = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetUserEmojiStatus(
      userId: $userId,
      emojiStatusCustomEmojiId: $emojiStatusCustomEmojiId,
      emojiStatusExpirationDate: $emojiStatusExpirationDate,
    ), $timeout);
  }
  public function getFile(
    string $fileId,
    ?int $timeout = null,
  ): File {
    return $this(new GetFile(
      fileId: $fileId,
    ), $timeout);
  }
  public function banChatMember(
    int|string $chatId,
    int $userId,
    null|DateInterval|DateTime|int $untilDate = null,
    ?bool $revokeMessages = null,
    ?int $timeout = null,
  ): bool {
    return $this(new BanChatMember(
      chatId: $chatId,
      userId: $userId,
      untilDate: $untilDate,
      revokeMessages: $revokeMessages,
    ), $timeout);
  }
  public function unbanChatMember(
    int|string $chatId,
    int $userId,
    ?bool $onlyIfBanned = null,
    ?int $timeout = null,
  ): bool {
    return $this(new UnbanChatMember(
      chatId: $chatId,
      userId: $userId,
      onlyIfBanned: $onlyIfBanned,
    ), $timeout);
  }
  public function restrictChatMember(
    int|string $chatId,
    int $userId,
    ChatPermissions $permissions,
    ?bool $useIndependentChatPermissions = null,
    null|DateInterval|DateTime|int $untilDate = null,
    ?int $timeout = null,
  ): bool {
    return $this(new RestrictChatMember(
      chatId: $chatId,
      userId: $userId,
      permissions: $permissions,
      useIndependentChatPermissions: $useIndependentChatPermissions,
      untilDate: $untilDate,
    ), $timeout);
  }
  public function promoteChatMember(
    int|string $chatId,
    int $userId,
    ?bool $isAnonymous = null,
    ?bool $canManageChat = null,
    ?bool $canDeleteMessages = null,
    ?bool $canManageVideoChats = null,
    ?bool $canRestrictMembers = null,
    ?bool $canPromoteMembers = null,
    ?bool $canChangeInfo = null,
    ?bool $canInviteUsers = null,
    ?bool $canPostStories = null,
    ?bool $canEditStories = null,
    ?bool $canDeleteStories = null,
    ?bool $canPostMessages = null,
    ?bool $canEditMessages = null,
    ?bool $canPinMessages = null,
    ?bool $canManageTopics = null,
    ?bool $canManageDirectMessages = null,
    ?bool $canManageTags = null,
    ?int $timeout = null,
  ): bool {
    return $this(new PromoteChatMember(
      chatId: $chatId,
      userId: $userId,
      isAnonymous: $isAnonymous,
      canManageChat: $canManageChat,
      canDeleteMessages: $canDeleteMessages,
      canManageVideoChats: $canManageVideoChats,
      canRestrictMembers: $canRestrictMembers,
      canPromoteMembers: $canPromoteMembers,
      canChangeInfo: $canChangeInfo,
      canInviteUsers: $canInviteUsers,
      canPostStories: $canPostStories,
      canEditStories: $canEditStories,
      canDeleteStories: $canDeleteStories,
      canPostMessages: $canPostMessages,
      canEditMessages: $canEditMessages,
      canPinMessages: $canPinMessages,
      canManageTopics: $canManageTopics,
      canManageDirectMessages: $canManageDirectMessages,
      canManageTags: $canManageTags,
    ), $timeout);
  }
  public function setChatAdministratorCustomTitle(
    int|string $chatId,
    int $userId,
    string $customTitle,
    ?int $timeout = null,
  ): bool {
    return $this(new SetChatAdministratorCustomTitle(
      chatId: $chatId,
      userId: $userId,
      customTitle: $customTitle,
    ), $timeout);
  }
  public function setChatMemberTag(
    int|string $chatId,
    int $userId,
    ?string $tag = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetChatMemberTag(
      chatId: $chatId,
      userId: $userId,
      tag: $tag,
    ), $timeout);
  }
  public function banChatSenderChat(
    int|string $chatId,
    int $senderChatId,
    ?int $timeout = null,
  ): bool {
    return $this(new BanChatSenderChat(
      chatId: $chatId,
      senderChatId: $senderChatId,
    ), $timeout);
  }
  public function unbanChatSenderChat(
    int|string $chatId,
    int $senderChatId,
    ?int $timeout = null,
  ): bool {
    return $this(new UnbanChatSenderChat(
      chatId: $chatId,
      senderChatId: $senderChatId,
    ), $timeout);
  }
  public function setChatPermissions(
    int|string $chatId,
    ChatPermissions $permissions,
    ?bool $useIndependentChatPermissions = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetChatPermissions(
      chatId: $chatId,
      permissions: $permissions,
      useIndependentChatPermissions: $useIndependentChatPermissions,
    ), $timeout);
  }
  public function exportChatInviteLink(
    int|string $chatId,
    ?int $timeout = null,
  ): string {
    return $this(new ExportChatInviteLink(
      chatId: $chatId,
    ), $timeout);
  }
  public function createChatInviteLink(
    int|string $chatId,
    ?string $name = null,
    null|DateInterval|DateTime|int $expireDate = null,
    ?int $memberLimit = null,
    ?bool $createsJoinRequest = null,
    ?int $timeout = null,
  ): ChatInviteLink {
    return $this(new CreateChatInviteLink(
      chatId: $chatId,
      name: $name,
      expireDate: $expireDate,
      memberLimit: $memberLimit,
      createsJoinRequest: $createsJoinRequest,
    ), $timeout);
  }
  public function editChatInviteLink(
    int|string $chatId,
    string $inviteLink,
    ?string $name = null,
    null|DateInterval|DateTime|int $expireDate = null,
    ?int $memberLimit = null,
    ?bool $createsJoinRequest = null,
    ?int $timeout = null,
  ): ChatInviteLink {
    return $this(new EditChatInviteLink(
      chatId: $chatId,
      inviteLink: $inviteLink,
      name: $name,
      expireDate: $expireDate,
      memberLimit: $memberLimit,
      createsJoinRequest: $createsJoinRequest,
    ), $timeout);
  }
  public function createChatSubscriptionInviteLink(
    int|string $chatId,
    DateInterval|DateTime|int $subscriptionPeriod,
    int $subscriptionPrice,
    ?string $name = null,
    ?int $timeout = null,
  ): ChatInviteLink {
    return $this(new CreateChatSubscriptionInviteLink(
      chatId: $chatId,
      subscriptionPeriod: $subscriptionPeriod,
      subscriptionPrice: $subscriptionPrice,
      name: $name,
    ), $timeout);
  }
  public function editChatSubscriptionInviteLink(
    int|string $chatId,
    string $inviteLink,
    ?string $name = null,
    ?int $timeout = null,
  ): ChatInviteLink {
    return $this(new EditChatSubscriptionInviteLink(
      chatId: $chatId,
      inviteLink: $inviteLink,
      name: $name,
    ), $timeout);
  }
  public function revokeChatInviteLink(
    int|string $chatId,
    string $inviteLink,
    ?int $timeout = null,
  ): ChatInviteLink {
    return $this(new RevokeChatInviteLink(
      chatId: $chatId,
      inviteLink: $inviteLink,
    ), $timeout);
  }
  public function approveChatJoinRequest(
    int|string $chatId,
    int $userId,
    ?int $timeout = null,
  ): bool {
    return $this(new ApproveChatJoinRequest(
      chatId: $chatId,
      userId: $userId,
    ), $timeout);
  }
  public function declineChatJoinRequest(
    int|string $chatId,
    int $userId,
    ?int $timeout = null,
  ): bool {
    return $this(new DeclineChatJoinRequest(
      chatId: $chatId,
      userId: $userId,
    ), $timeout);
  }
  public function setChatPhoto(
    int|string $chatId,
    InputFile $photo,
    ?int $timeout = null,
  ): bool {
    return $this(new SetChatPhoto(
      chatId: $chatId,
      photo: $photo,
    ), $timeout);
  }
  public function deleteChatPhoto(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteChatPhoto(
      chatId: $chatId,
    ), $timeout);
  }
  public function setChatTitle(
    int|string $chatId,
    string $title,
    ?int $timeout = null,
  ): bool {
    return $this(new SetChatTitle(
      chatId: $chatId,
      title: $title,
    ), $timeout);
  }
  public function setChatDescription(
    int|string $chatId,
    ?string $description = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetChatDescription(
      chatId: $chatId,
      description: $description,
    ), $timeout);
  }
  public function pinChatMessage(
    int|string $chatId,
    int $messageId,
    ?string $businessConnectionId = null,
    ?bool $disableNotification = null,
    ?int $timeout = null,
  ): bool {
    return $this(new PinChatMessage(
      chatId: $chatId,
      messageId: $messageId,
      businessConnectionId: $businessConnectionId,
      disableNotification: $disableNotification,
    ), $timeout);
  }
  public function unpinChatMessage(
    int|string $chatId,
    ?string $businessConnectionId = null,
    ?int $messageId = null,
    ?int $timeout = null,
  ): bool {
    return $this(new UnpinChatMessage(
      chatId: $chatId,
      businessConnectionId: $businessConnectionId,
      messageId: $messageId,
    ), $timeout);
  }
  public function unpinAllChatMessages(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new UnpinAllChatMessages(
      chatId: $chatId,
    ), $timeout);
  }
  public function leaveChat(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new LeaveChat(
      chatId: $chatId,
    ), $timeout);
  }
  public function getChat(
    int|string $chatId,
    ?int $timeout = null,
  ): ChatFullInfo {
    return $this(new GetChat(
      chatId: $chatId,
    ), $timeout);
  }

  /**
   * @return list<ChatMember>
   */
  public function getChatAdministrators(
    int|string $chatId,
    ?bool $returnBots = null,
    ?int $timeout = null,
  ): array {
    /** @var list<ChatMember> */
    return $this(new GetChatAdministrators(
      chatId: $chatId,
      returnBots: $returnBots,
    ), $timeout);
  }
  public function getChatMemberCount(
    int|string $chatId,
    ?int $timeout = null,
  ): int {
    return $this(new GetChatMemberCount(
      chatId: $chatId,
    ), $timeout);
  }
  public function getChatMember(
    int|string $chatId,
    int $userId,
    ?int $timeout = null,
  ): ChatMember {
    return $this(new GetChatMember(
      chatId: $chatId,
      userId: $userId,
    ), $timeout);
  }

  /**
   * @return list<Message>
   */
  public function getUserPersonalChatMessages(
    int $userId,
    int $limit,
    ?int $timeout = null,
  ): array {
    /** @var list<Message> */
    return $this(new GetUserPersonalChatMessages(
      userId: $userId,
      limit: $limit,
    ), $timeout);
  }
  public function setChatStickerSet(
    int|string $chatId,
    string $stickerSetName,
    ?int $timeout = null,
  ): bool {
    return $this(new SetChatStickerSet(
      chatId: $chatId,
      stickerSetName: $stickerSetName,
    ), $timeout);
  }
  public function deleteChatStickerSet(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteChatStickerSet(
      chatId: $chatId,
    ), $timeout);
  }

  /**
   * @return list<Sticker>
   */
  public function getForumTopicIconStickers(
    ?int $timeout = null,
  ): array {
    /** @var list<Sticker> */
    return $this(new GetForumTopicIconStickers(
    ), $timeout);
  }
  public function createForumTopic(
    int|string $chatId,
    string $name,
    ?int $iconColor = null,
    ?string $iconCustomEmojiId = null,
    ?int $timeout = null,
  ): ForumTopic {
    return $this(new CreateForumTopic(
      chatId: $chatId,
      name: $name,
      iconColor: $iconColor,
      iconCustomEmojiId: $iconCustomEmojiId,
    ), $timeout);
  }
  public function editForumTopic(
    int|string $chatId,
    int $messageThreadId,
    ?string $name = null,
    ?string $iconCustomEmojiId = null,
    ?int $timeout = null,
  ): bool {
    return $this(new EditForumTopic(
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      name: $name,
      iconCustomEmojiId: $iconCustomEmojiId,
    ), $timeout);
  }
  public function closeForumTopic(
    int|string $chatId,
    int $messageThreadId,
    ?int $timeout = null,
  ): bool {
    return $this(new CloseForumTopic(
      chatId: $chatId,
      messageThreadId: $messageThreadId,
    ), $timeout);
  }
  public function reopenForumTopic(
    int|string $chatId,
    int $messageThreadId,
    ?int $timeout = null,
  ): bool {
    return $this(new ReopenForumTopic(
      chatId: $chatId,
      messageThreadId: $messageThreadId,
    ), $timeout);
  }
  public function deleteForumTopic(
    int|string $chatId,
    int $messageThreadId,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteForumTopic(
      chatId: $chatId,
      messageThreadId: $messageThreadId,
    ), $timeout);
  }
  public function unpinAllForumTopicMessages(
    int|string $chatId,
    int $messageThreadId,
    ?int $timeout = null,
  ): bool {
    return $this(new UnpinAllForumTopicMessages(
      chatId: $chatId,
      messageThreadId: $messageThreadId,
    ), $timeout);
  }
  public function editGeneralForumTopic(
    int|string $chatId,
    string $name,
    ?int $timeout = null,
  ): bool {
    return $this(new EditGeneralForumTopic(
      chatId: $chatId,
      name: $name,
    ), $timeout);
  }
  public function closeGeneralForumTopic(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new CloseGeneralForumTopic(
      chatId: $chatId,
    ), $timeout);
  }
  public function reopenGeneralForumTopic(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new ReopenGeneralForumTopic(
      chatId: $chatId,
    ), $timeout);
  }
  public function hideGeneralForumTopic(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new HideGeneralForumTopic(
      chatId: $chatId,
    ), $timeout);
  }
  public function unhideGeneralForumTopic(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new UnhideGeneralForumTopic(
      chatId: $chatId,
    ), $timeout);
  }
  public function unpinAllGeneralForumTopicMessages(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new UnpinAllGeneralForumTopicMessages(
      chatId: $chatId,
    ), $timeout);
  }
  public function answerCallbackQuery(
    string $callbackQueryId,
    ?string $text = null,
    ?bool $showAlert = null,
    ?string $url = null,
    ?int $cacheTime = null,
    ?int $timeout = null,
  ): bool {
    return $this(new AnswerCallbackQuery(
      callbackQueryId: $callbackQueryId,
      text: $text,
      showAlert: $showAlert,
      url: $url,
      cacheTime: $cacheTime,
    ), $timeout);
  }
  public function answerGuestQuery(
    string $guestQueryId,
    InlineQueryResult $result,
    ?int $timeout = null,
  ): SentGuestMessage {
    return $this(new AnswerGuestQuery(
      guestQueryId: $guestQueryId,
      result: $result,
    ), $timeout);
  }
  public function getUserChatBoosts(
    int|string $chatId,
    int $userId,
    ?int $timeout = null,
  ): UserChatBoosts {
    return $this(new GetUserChatBoosts(
      chatId: $chatId,
      userId: $userId,
    ), $timeout);
  }
  public function getBusinessConnection(
    string $businessConnectionId,
    ?int $timeout = null,
  ): BusinessConnection {
    return $this(new GetBusinessConnection(
      businessConnectionId: $businessConnectionId,
    ), $timeout);
  }
  public function getManagedBotToken(
    int $userId,
    ?int $timeout = null,
  ): string {
    return $this(new GetManagedBotToken(
      userId: $userId,
    ), $timeout);
  }
  public function replaceManagedBotToken(
    int $userId,
    ?int $timeout = null,
  ): string {
    return $this(new ReplaceManagedBotToken(
      userId: $userId,
    ), $timeout);
  }
  public function getManagedBotAccessSettings(
    int $userId,
    ?int $timeout = null,
  ): BotAccessSettings {
    return $this(new GetManagedBotAccessSettings(
      userId: $userId,
    ), $timeout);
  }

  /**
   * @param null|list<int> $addedUserIds
   */
  public function setManagedBotAccessSettings(
    int $userId,
    bool $isAccessRestricted,
    ?array $addedUserIds = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetManagedBotAccessSettings(
      userId: $userId,
      isAccessRestricted: $isAccessRestricted,
      addedUserIds: $addedUserIds,
    ), $timeout);
  }

  /**
   * @param list<BotCommand> $commands
   */
  public function setMyCommands(
    array $commands,
    ?BotCommandScope $scope = null,
    ?string $languageCode = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetMyCommands(
      commands: $commands,
      scope: $scope,
      languageCode: $languageCode,
    ), $timeout);
  }
  public function deleteMyCommands(
    ?BotCommandScope $scope = null,
    ?string $languageCode = null,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteMyCommands(
      scope: $scope,
      languageCode: $languageCode,
    ), $timeout);
  }

  /**
   * @return list<BotCommand>
   */
  public function getMyCommands(
    ?BotCommandScope $scope = null,
    ?string $languageCode = null,
    ?int $timeout = null,
  ): array {
    /** @var list<BotCommand> */
    return $this(new GetMyCommands(
      scope: $scope,
      languageCode: $languageCode,
    ), $timeout);
  }
  public function setMyName(
    ?string $name = null,
    ?string $languageCode = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetMyName(
      name: $name,
      languageCode: $languageCode,
    ), $timeout);
  }
  public function getMyName(
    ?string $languageCode = null,
    ?int $timeout = null,
  ): BotName {
    return $this(new GetMyName(
      languageCode: $languageCode,
    ), $timeout);
  }
  public function setMyDescription(
    ?string $description = null,
    ?string $languageCode = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetMyDescription(
      description: $description,
      languageCode: $languageCode,
    ), $timeout);
  }
  public function getMyDescription(
    ?string $languageCode = null,
    ?int $timeout = null,
  ): BotDescription {
    return $this(new GetMyDescription(
      languageCode: $languageCode,
    ), $timeout);
  }
  public function setMyShortDescription(
    ?string $shortDescription = null,
    ?string $languageCode = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetMyShortDescription(
      shortDescription: $shortDescription,
      languageCode: $languageCode,
    ), $timeout);
  }
  public function getMyShortDescription(
    ?string $languageCode = null,
    ?int $timeout = null,
  ): BotShortDescription {
    return $this(new GetMyShortDescription(
      languageCode: $languageCode,
    ), $timeout);
  }
  public function setMyProfilePhoto(
    InputProfilePhoto $photo,
    ?int $timeout = null,
  ): bool {
    return $this(new SetMyProfilePhoto(
      photo: $photo,
    ), $timeout);
  }
  public function removeMyProfilePhoto(
    ?int $timeout = null,
  ): bool {
    return $this(new RemoveMyProfilePhoto(
    ), $timeout);
  }
  public function setChatMenuButton(
    ?int $chatId = null,
    null|MenuButtonCommands|MenuButtonDefault|MenuButtonWebApp $menuButton = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetChatMenuButton(
      chatId: $chatId,
      menuButton: $menuButton,
    ), $timeout);
  }
  public function getChatMenuButton(
    ?int $chatId = null,
    ?int $timeout = null,
  ): MenuButton {
    return $this(new GetChatMenuButton(
      chatId: $chatId,
    ), $timeout);
  }
  public function setMyDefaultAdministratorRights(
    ?ChatAdministratorRights $rights = null,
    ?bool $forChannels = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetMyDefaultAdministratorRights(
      rights: $rights,
      forChannels: $forChannels,
    ), $timeout);
  }
  public function getMyDefaultAdministratorRights(
    ?bool $forChannels = null,
    ?int $timeout = null,
  ): ChatAdministratorRights {
    return $this(new GetMyDefaultAdministratorRights(
      forChannels: $forChannels,
    ), $timeout);
  }
  public function getAvailableGifts(
    ?int $timeout = null,
  ): Gifts {
    return $this(new GetAvailableGifts(
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $textEntities
   */
  public function sendGift(
    string $giftId,
    ?int $userId = null,
    null|int|string $chatId = null,
    ?bool $payForUpgrade = null,
    ?string $text = null,
    ?string $textParseMode = null,
    ?array $textEntities = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SendGift(
      giftId: $giftId,
      userId: $userId,
      chatId: $chatId,
      payForUpgrade: $payForUpgrade,
      text: $text,
      textParseMode: $textParseMode,
      textEntities: $textEntities,
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $textEntities
   */
  public function giftPremiumSubscription(
    int $userId,
    int $monthCount,
    int $starCount,
    ?string $text = null,
    ?string $textParseMode = null,
    ?array $textEntities = null,
    ?int $timeout = null,
  ): bool {
    return $this(new GiftPremiumSubscription(
      userId: $userId,
      monthCount: $monthCount,
      starCount: $starCount,
      text: $text,
      textParseMode: $textParseMode,
      textEntities: $textEntities,
    ), $timeout);
  }
  public function verifyUser(
    int $userId,
    ?string $customDescription = null,
    ?int $timeout = null,
  ): bool {
    return $this(new VerifyUser(
      userId: $userId,
      customDescription: $customDescription,
    ), $timeout);
  }
  public function verifyChat(
    int|string $chatId,
    ?string $customDescription = null,
    ?int $timeout = null,
  ): bool {
    return $this(new VerifyChat(
      chatId: $chatId,
      customDescription: $customDescription,
    ), $timeout);
  }
  public function removeUserVerification(
    int $userId,
    ?int $timeout = null,
  ): bool {
    return $this(new RemoveUserVerification(
      userId: $userId,
    ), $timeout);
  }
  public function removeChatVerification(
    int|string $chatId,
    ?int $timeout = null,
  ): bool {
    return $this(new RemoveChatVerification(
      chatId: $chatId,
    ), $timeout);
  }
  public function readBusinessMessage(
    string $businessConnectionId,
    int $chatId,
    int $messageId,
    ?int $timeout = null,
  ): bool {
    return $this(new ReadBusinessMessage(
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      messageId: $messageId,
    ), $timeout);
  }

  /**
   * @param list<int> $messageIds
   */
  public function deleteBusinessMessages(
    string $businessConnectionId,
    array $messageIds,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteBusinessMessages(
      businessConnectionId: $businessConnectionId,
      messageIds: $messageIds,
    ), $timeout);
  }
  public function setBusinessAccountName(
    string $businessConnectionId,
    string $firstName,
    ?string $lastName = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetBusinessAccountName(
      businessConnectionId: $businessConnectionId,
      firstName: $firstName,
      lastName: $lastName,
    ), $timeout);
  }
  public function setBusinessAccountUsername(
    string $businessConnectionId,
    ?string $username = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetBusinessAccountUsername(
      businessConnectionId: $businessConnectionId,
      username: $username,
    ), $timeout);
  }
  public function setBusinessAccountBio(
    string $businessConnectionId,
    ?string $bio = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetBusinessAccountBio(
      businessConnectionId: $businessConnectionId,
      bio: $bio,
    ), $timeout);
  }
  public function setBusinessAccountProfilePhoto(
    string $businessConnectionId,
    InputProfilePhoto $photo,
    ?bool $isPublic = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetBusinessAccountProfilePhoto(
      businessConnectionId: $businessConnectionId,
      photo: $photo,
      isPublic: $isPublic,
    ), $timeout);
  }
  public function removeBusinessAccountProfilePhoto(
    string $businessConnectionId,
    ?bool $isPublic = null,
    ?int $timeout = null,
  ): bool {
    return $this(new RemoveBusinessAccountProfilePhoto(
      businessConnectionId: $businessConnectionId,
      isPublic: $isPublic,
    ), $timeout);
  }
  public function setBusinessAccountGiftSettings(
    string $businessConnectionId,
    bool $showGiftButton,
    AcceptedGiftTypes $acceptedGiftTypes,
    ?int $timeout = null,
  ): bool {
    return $this(new SetBusinessAccountGiftSettings(
      businessConnectionId: $businessConnectionId,
      showGiftButton: $showGiftButton,
      acceptedGiftTypes: $acceptedGiftTypes,
    ), $timeout);
  }
  public function getBusinessAccountStarBalance(
    string $businessConnectionId,
    ?int $timeout = null,
  ): StarAmount {
    return $this(new GetBusinessAccountStarBalance(
      businessConnectionId: $businessConnectionId,
    ), $timeout);
  }
  public function transferBusinessAccountStars(
    string $businessConnectionId,
    int $starCount,
    ?int $timeout = null,
  ): bool {
    return $this(new TransferBusinessAccountStars(
      businessConnectionId: $businessConnectionId,
      starCount: $starCount,
    ), $timeout);
  }
  public function getBusinessAccountGifts(
    string $businessConnectionId,
    ?bool $excludeUnsaved = null,
    ?bool $excludeSaved = null,
    ?bool $excludeUnlimited = null,
    ?bool $excludeLimitedUpgradable = null,
    ?bool $excludeLimitedNonUpgradable = null,
    ?bool $excludeUnique = null,
    ?bool $excludeFromBlockchain = null,
    ?bool $sortByPrice = null,
    ?string $offset = null,
    ?int $limit = null,
    ?int $timeout = null,
  ): OwnedGifts {
    return $this(new GetBusinessAccountGifts(
      businessConnectionId: $businessConnectionId,
      excludeUnsaved: $excludeUnsaved,
      excludeSaved: $excludeSaved,
      excludeUnlimited: $excludeUnlimited,
      excludeLimitedUpgradable: $excludeLimitedUpgradable,
      excludeLimitedNonUpgradable: $excludeLimitedNonUpgradable,
      excludeUnique: $excludeUnique,
      excludeFromBlockchain: $excludeFromBlockchain,
      sortByPrice: $sortByPrice,
      offset: $offset,
      limit: $limit,
    ), $timeout);
  }
  public function getUserGifts(
    int $userId,
    ?bool $excludeUnlimited = null,
    ?bool $excludeLimitedUpgradable = null,
    ?bool $excludeLimitedNonUpgradable = null,
    ?bool $excludeFromBlockchain = null,
    ?bool $excludeUnique = null,
    ?bool $sortByPrice = null,
    ?string $offset = null,
    ?int $limit = null,
    ?int $timeout = null,
  ): OwnedGifts {
    return $this(new GetUserGifts(
      userId: $userId,
      excludeUnlimited: $excludeUnlimited,
      excludeLimitedUpgradable: $excludeLimitedUpgradable,
      excludeLimitedNonUpgradable: $excludeLimitedNonUpgradable,
      excludeFromBlockchain: $excludeFromBlockchain,
      excludeUnique: $excludeUnique,
      sortByPrice: $sortByPrice,
      offset: $offset,
      limit: $limit,
    ), $timeout);
  }
  public function getChatGifts(
    int|string $chatId,
    ?bool $excludeUnsaved = null,
    ?bool $excludeSaved = null,
    ?bool $excludeUnlimited = null,
    ?bool $excludeLimitedUpgradable = null,
    ?bool $excludeLimitedNonUpgradable = null,
    ?bool $excludeFromBlockchain = null,
    ?bool $excludeUnique = null,
    ?bool $sortByPrice = null,
    ?string $offset = null,
    ?int $limit = null,
    ?int $timeout = null,
  ): OwnedGifts {
    return $this(new GetChatGifts(
      chatId: $chatId,
      excludeUnsaved: $excludeUnsaved,
      excludeSaved: $excludeSaved,
      excludeUnlimited: $excludeUnlimited,
      excludeLimitedUpgradable: $excludeLimitedUpgradable,
      excludeLimitedNonUpgradable: $excludeLimitedNonUpgradable,
      excludeFromBlockchain: $excludeFromBlockchain,
      excludeUnique: $excludeUnique,
      sortByPrice: $sortByPrice,
      offset: $offset,
      limit: $limit,
    ), $timeout);
  }
  public function convertGiftToStars(
    string $businessConnectionId,
    string $ownedGiftId,
    ?int $timeout = null,
  ): bool {
    return $this(new ConvertGiftToStars(
      businessConnectionId: $businessConnectionId,
      ownedGiftId: $ownedGiftId,
    ), $timeout);
  }
  public function upgradeGift(
    string $businessConnectionId,
    string $ownedGiftId,
    ?bool $keepOriginalDetails = null,
    ?int $starCount = null,
    ?int $timeout = null,
  ): bool {
    return $this(new UpgradeGift(
      businessConnectionId: $businessConnectionId,
      ownedGiftId: $ownedGiftId,
      keepOriginalDetails: $keepOriginalDetails,
      starCount: $starCount,
    ), $timeout);
  }
  public function transferGift(
    string $businessConnectionId,
    string $ownedGiftId,
    int $newOwnerChatId,
    ?int $starCount = null,
    ?int $timeout = null,
  ): bool {
    return $this(new TransferGift(
      businessConnectionId: $businessConnectionId,
      ownedGiftId: $ownedGiftId,
      newOwnerChatId: $newOwnerChatId,
      starCount: $starCount,
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   * @param null|list<StoryArea> $areas
   */
  public function postStory(
    string $businessConnectionId,
    InputStoryContent $content,
    int $activePeriod,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?array $areas = null,
    ?bool $postToChatPage = null,
    ?bool $protectContent = null,
    ?int $timeout = null,
  ): Story {
    return $this(new PostStory(
      businessConnectionId: $businessConnectionId,
      content: $content,
      activePeriod: $activePeriod,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      areas: $areas,
      postToChatPage: $postToChatPage,
      protectContent: $protectContent,
    ), $timeout);
  }
  public function repostStory(
    string $businessConnectionId,
    int $fromChatId,
    int $fromStoryId,
    int $activePeriod,
    ?bool $postToChatPage = null,
    ?bool $protectContent = null,
    ?int $timeout = null,
  ): Story {
    return $this(new RepostStory(
      businessConnectionId: $businessConnectionId,
      fromChatId: $fromChatId,
      fromStoryId: $fromStoryId,
      activePeriod: $activePeriod,
      postToChatPage: $postToChatPage,
      protectContent: $protectContent,
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   * @param null|list<StoryArea> $areas
   */
  public function editStory(
    string $businessConnectionId,
    int $storyId,
    InputStoryContent $content,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?array $areas = null,
    ?int $timeout = null,
  ): Story {
    return $this(new EditStory(
      businessConnectionId: $businessConnectionId,
      storyId: $storyId,
      content: $content,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      areas: $areas,
    ), $timeout);
  }
  public function deleteStory(
    string $businessConnectionId,
    int $storyId,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteStory(
      businessConnectionId: $businessConnectionId,
      storyId: $storyId,
    ), $timeout);
  }
  public function answerWebAppQuery(
    string $webAppQueryId,
    InlineQueryResult $result,
    ?int $timeout = null,
  ): SentWebAppMessage {
    return $this(new AnswerWebAppQuery(
      webAppQueryId: $webAppQueryId,
      result: $result,
    ), $timeout);
  }
  public function savePreparedInlineMessage(
    int $userId,
    InlineQueryResult $result,
    ?bool $allowUserChats = null,
    ?bool $allowBotChats = null,
    ?bool $allowGroupChats = null,
    ?bool $allowChannelChats = null,
    ?int $timeout = null,
  ): PreparedInlineMessage {
    return $this(new SavePreparedInlineMessage(
      userId: $userId,
      result: $result,
      allowUserChats: $allowUserChats,
      allowBotChats: $allowBotChats,
      allowGroupChats: $allowGroupChats,
      allowChannelChats: $allowChannelChats,
    ), $timeout);
  }
  public function savePreparedKeyboardButton(
    int $userId,
    KeyboardButton $button,
    ?int $timeout = null,
  ): PreparedKeyboardButton {
    return $this(new SavePreparedKeyboardButton(
      userId: $userId,
      button: $button,
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $entities
   */
  public function editMessageText(
    string $text,
    ?string $businessConnectionId = null,
    null|int|string $chatId = null,
    ?int $messageId = null,
    ?string $inlineMessageId = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $entities = null,
    null|BotDefault|LinkPreviewOptions $linkPreviewOptions = new BotDefault('link_preview'),
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new EditMessageText(
      text: $text,
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      messageId: $messageId,
      inlineMessageId: $inlineMessageId,
      parseMode: $parseMode,
      entities: $entities,
      linkPreviewOptions: $linkPreviewOptions,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function editMessageCaption(
    ?string $businessConnectionId = null,
    null|int|string $chatId = null,
    ?int $messageId = null,
    ?string $inlineMessageId = null,
    ?string $caption = null,
    null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?array $captionEntities = null,
    null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new EditMessageCaption(
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      messageId: $messageId,
      inlineMessageId: $inlineMessageId,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function editMessageMedia(
    InputMedia $media,
    ?string $businessConnectionId = null,
    null|int|string $chatId = null,
    ?int $messageId = null,
    ?string $inlineMessageId = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new EditMessageMedia(
      media: $media,
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      messageId: $messageId,
      inlineMessageId: $inlineMessageId,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function editMessageLiveLocation(
    float $latitude,
    float $longitude,
    ?string $businessConnectionId = null,
    null|int|string $chatId = null,
    ?int $messageId = null,
    ?string $inlineMessageId = null,
    ?int $livePeriod = null,
    ?float $horizontalAccuracy = null,
    ?int $heading = null,
    ?int $proximityAlertRadius = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new EditMessageLiveLocation(
      latitude: $latitude,
      longitude: $longitude,
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      messageId: $messageId,
      inlineMessageId: $inlineMessageId,
      livePeriod: $livePeriod,
      horizontalAccuracy: $horizontalAccuracy,
      heading: $heading,
      proximityAlertRadius: $proximityAlertRadius,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function stopMessageLiveLocation(
    ?string $businessConnectionId = null,
    null|int|string $chatId = null,
    ?int $messageId = null,
    ?string $inlineMessageId = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new StopMessageLiveLocation(
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      messageId: $messageId,
      inlineMessageId: $inlineMessageId,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function editMessageChecklist(
    string $businessConnectionId,
    int|string $chatId,
    int $messageId,
    InputChecklist $checklist,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new EditMessageChecklist(
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      messageId: $messageId,
      checklist: $checklist,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function editMessageReplyMarkup(
    ?string $businessConnectionId = null,
    null|int|string $chatId = null,
    ?int $messageId = null,
    ?string $inlineMessageId = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new EditMessageReplyMarkup(
      businessConnectionId: $businessConnectionId,
      chatId: $chatId,
      messageId: $messageId,
      inlineMessageId: $inlineMessageId,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function stopPoll(
    int|string $chatId,
    int $messageId,
    ?string $businessConnectionId = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Poll {
    return $this(new StopPoll(
      chatId: $chatId,
      messageId: $messageId,
      businessConnectionId: $businessConnectionId,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function approveSuggestedPost(
    int $chatId,
    int $messageId,
    null|DateInterval|DateTime|int $sendDate = null,
    ?int $timeout = null,
  ): bool {
    return $this(new ApproveSuggestedPost(
      chatId: $chatId,
      messageId: $messageId,
      sendDate: $sendDate,
    ), $timeout);
  }
  public function declineSuggestedPost(
    int $chatId,
    int $messageId,
    ?string $comment = null,
    ?int $timeout = null,
  ): bool {
    return $this(new DeclineSuggestedPost(
      chatId: $chatId,
      messageId: $messageId,
      comment: $comment,
    ), $timeout);
  }
  public function deleteMessage(
    int|string $chatId,
    int $messageId,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteMessage(
      chatId: $chatId,
      messageId: $messageId,
    ), $timeout);
  }

  /**
   * @param list<int> $messageIds
   */
  public function deleteMessages(
    int|string $chatId,
    array $messageIds,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteMessages(
      chatId: $chatId,
      messageIds: $messageIds,
    ), $timeout);
  }
  public function deleteMessageReaction(
    int|string $chatId,
    int $messageId,
    ?int $userId = null,
    ?int $actorChatId = null,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteMessageReaction(
      chatId: $chatId,
      messageId: $messageId,
      userId: $userId,
      actorChatId: $actorChatId,
    ), $timeout);
  }
  public function deleteAllMessageReactions(
    int|string $chatId,
    ?int $userId = null,
    ?int $actorChatId = null,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteAllMessageReactions(
      chatId: $chatId,
      userId: $userId,
      actorChatId: $actorChatId,
    ), $timeout);
  }
  public function sendSticker(
    int|string $chatId,
    InputFile|string $sticker,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?int $directMessagesTopicId = null,
    ?string $emoji = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendSticker(
      chatId: $chatId,
      sticker: $sticker,
      businessConnectionId: $businessConnectionId,
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
    ), $timeout);
  }
  public function getStickerSet(
    string $name,
    ?int $timeout = null,
  ): StickerSet {
    return $this(new GetStickerSet(
      name: $name,
    ), $timeout);
  }

  /**
   * @param list<string> $customEmojiIds
   *
   * @return list<Sticker>
   */
  public function getCustomEmojiStickers(
    array $customEmojiIds,
    ?int $timeout = null,
  ): array {
    /** @var list<Sticker> */
    return $this(new GetCustomEmojiStickers(
      customEmojiIds: $customEmojiIds,
    ), $timeout);
  }
  public function uploadStickerFile(
    int $userId,
    InputFile $sticker,
    string $stickerFormat,
    ?int $timeout = null,
  ): File {
    return $this(new UploadStickerFile(
      userId: $userId,
      sticker: $sticker,
      stickerFormat: $stickerFormat,
    ), $timeout);
  }

  /**
   * @param list<InputSticker> $stickers
   */
  public function createNewStickerSet(
    int $userId,
    string $name,
    string $title,
    array $stickers,
    ?string $stickerType = null,
    ?bool $needsRepainting = null,
    ?int $timeout = null,
  ): bool {
    return $this(new CreateNewStickerSet(
      userId: $userId,
      name: $name,
      title: $title,
      stickers: $stickers,
      stickerType: $stickerType,
      needsRepainting: $needsRepainting,
    ), $timeout);
  }
  public function addStickerToSet(
    int $userId,
    string $name,
    InputSticker $sticker,
    ?int $timeout = null,
  ): bool {
    return $this(new AddStickerToSet(
      userId: $userId,
      name: $name,
      sticker: $sticker,
    ), $timeout);
  }
  public function setStickerPositionInSet(
    string $sticker,
    int $position,
    ?int $timeout = null,
  ): bool {
    return $this(new SetStickerPositionInSet(
      sticker: $sticker,
      position: $position,
    ), $timeout);
  }
  public function deleteStickerFromSet(
    string $sticker,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteStickerFromSet(
      sticker: $sticker,
    ), $timeout);
  }
  public function replaceStickerInSet(
    int $userId,
    string $name,
    string $oldSticker,
    InputSticker $sticker,
    ?int $timeout = null,
  ): bool {
    return $this(new ReplaceStickerInSet(
      userId: $userId,
      name: $name,
      oldSticker: $oldSticker,
      sticker: $sticker,
    ), $timeout);
  }

  /**
   * @param list<string> $emojiList
   */
  public function setStickerEmojiList(
    string $sticker,
    array $emojiList,
    ?int $timeout = null,
  ): bool {
    return $this(new SetStickerEmojiList(
      sticker: $sticker,
      emojiList: $emojiList,
    ), $timeout);
  }

  /**
   * @param null|list<string> $keywords
   */
  public function setStickerKeywords(
    string $sticker,
    ?array $keywords = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetStickerKeywords(
      sticker: $sticker,
      keywords: $keywords,
    ), $timeout);
  }
  public function setStickerMaskPosition(
    string $sticker,
    ?MaskPosition $maskPosition = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetStickerMaskPosition(
      sticker: $sticker,
      maskPosition: $maskPosition,
    ), $timeout);
  }
  public function setStickerSetTitle(
    string $name,
    string $title,
    ?int $timeout = null,
  ): bool {
    return $this(new SetStickerSetTitle(
      name: $name,
      title: $title,
    ), $timeout);
  }
  public function setStickerSetThumbnail(
    string $name,
    int $userId,
    string $format,
    null|InputFile|string $thumbnail = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetStickerSetThumbnail(
      name: $name,
      userId: $userId,
      format: $format,
      thumbnail: $thumbnail,
    ), $timeout);
  }
  public function setCustomEmojiStickerSetThumbnail(
    string $name,
    ?string $customEmojiId = null,
    ?int $timeout = null,
  ): bool {
    return $this(new SetCustomEmojiStickerSetThumbnail(
      name: $name,
      customEmojiId: $customEmojiId,
    ), $timeout);
  }
  public function deleteStickerSet(
    string $name,
    ?int $timeout = null,
  ): bool {
    return $this(new DeleteStickerSet(
      name: $name,
    ), $timeout);
  }

  /**
   * @param list<InlineQueryResult> $results
   */
  public function answerInlineQuery(
    string $inlineQueryId,
    array $results,
    ?int $cacheTime = null,
    ?bool $isPersonal = null,
    ?string $nextOffset = null,
    ?InlineQueryResultsButton $button = null,
    ?int $timeout = null,
  ): bool {
    return $this(new AnswerInlineQuery(
      inlineQueryId: $inlineQueryId,
      results: $results,
      cacheTime: $cacheTime,
      isPersonal: $isPersonal,
      nextOffset: $nextOffset,
      button: $button,
    ), $timeout);
  }

  /**
   * @param list<LabeledPrice> $prices
   * @param null|list<int> $suggestedTipAmounts
   */
  public function sendInvoice(
    int|string $chatId,
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
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?SuggestedPostParameters $suggestedPostParameters = null,
    ?ReplyParameters $replyParameters = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendInvoice(
      chatId: $chatId,
      title: $title,
      description: $description,
      payload: $payload,
      currency: $currency,
      prices: $prices,
      messageThreadId: $messageThreadId,
      directMessagesTopicId: $directMessagesTopicId,
      providerToken: $providerToken,
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
    ), $timeout);
  }

  /**
   * @param list<LabeledPrice> $prices
   * @param null|list<int> $suggestedTipAmounts
   */
  public function createInvoiceLink(
    string $title,
    string $description,
    string $payload,
    string $currency,
    array $prices,
    ?string $businessConnectionId = null,
    ?string $providerToken = null,
    ?int $subscriptionPeriod = null,
    ?int $maxTipAmount = null,
    ?array $suggestedTipAmounts = null,
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
    ?int $timeout = null,
  ): string {
    return $this(new CreateInvoiceLink(
      title: $title,
      description: $description,
      payload: $payload,
      currency: $currency,
      prices: $prices,
      businessConnectionId: $businessConnectionId,
      providerToken: $providerToken,
      subscriptionPeriod: $subscriptionPeriod,
      maxTipAmount: $maxTipAmount,
      suggestedTipAmounts: $suggestedTipAmounts,
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
    ), $timeout);
  }

  /**
   * @param null|list<ShippingOption> $shippingOptions
   */
  public function answerShippingQuery(
    string $shippingQueryId,
    bool $ok,
    ?array $shippingOptions = null,
    ?string $errorMessage = null,
    ?int $timeout = null,
  ): bool {
    return $this(new AnswerShippingQuery(
      shippingQueryId: $shippingQueryId,
      ok: $ok,
      shippingOptions: $shippingOptions,
      errorMessage: $errorMessage,
    ), $timeout);
  }
  public function answerPreCheckoutQuery(
    string $preCheckoutQueryId,
    bool $ok,
    ?string $errorMessage = null,
    ?int $timeout = null,
  ): bool {
    return $this(new AnswerPreCheckoutQuery(
      preCheckoutQueryId: $preCheckoutQueryId,
      ok: $ok,
      errorMessage: $errorMessage,
    ), $timeout);
  }
  public function getMyStarBalance(
    ?int $timeout = null,
  ): StarAmount {
    return $this(new GetMyStarBalance(
    ), $timeout);
  }
  public function getStarTransactions(
    ?int $offset = null,
    ?int $limit = null,
    ?int $timeout = null,
  ): StarTransactions {
    return $this(new GetStarTransactions(
      offset: $offset,
      limit: $limit,
    ), $timeout);
  }
  public function refundStarPayment(
    int $userId,
    string $telegramPaymentChargeId,
    ?int $timeout = null,
  ): bool {
    return $this(new RefundStarPayment(
      userId: $userId,
      telegramPaymentChargeId: $telegramPaymentChargeId,
    ), $timeout);
  }
  public function editUserStarSubscription(
    int $userId,
    string $telegramPaymentChargeId,
    bool $isCanceled,
    ?int $timeout = null,
  ): bool {
    return $this(new EditUserStarSubscription(
      userId: $userId,
      telegramPaymentChargeId: $telegramPaymentChargeId,
      isCanceled: $isCanceled,
    ), $timeout);
  }

  /**
   * @param list<PassportElementError> $errors
   */
  public function setPassportDataErrors(
    int $userId,
    array $errors,
    ?int $timeout = null,
  ): bool {
    return $this(new SetPassportDataErrors(
      userId: $userId,
      errors: $errors,
    ), $timeout);
  }
  public function sendGame(
    int|string $chatId,
    string $gameShortName,
    ?string $businessConnectionId = null,
    ?int $messageThreadId = null,
    ?bool $disableNotification = null,
    null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    ?bool $allowPaidBroadcast = null,
    ?string $messageEffectId = null,
    ?ReplyParameters $replyParameters = null,
    ?InlineKeyboardMarkup $replyMarkup = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SendGame(
      chatId: $chatId,
      gameShortName: $gameShortName,
      businessConnectionId: $businessConnectionId,
      messageThreadId: $messageThreadId,
      disableNotification: $disableNotification,
      protectContent: $protectContent,
      allowPaidBroadcast: $allowPaidBroadcast,
      messageEffectId: $messageEffectId,
      replyParameters: $replyParameters,
      replyMarkup: $replyMarkup,
    ), $timeout);
  }
  public function setGameScore(
    int $userId,
    int $score,
    ?bool $force = null,
    ?bool $disableEditMessage = null,
    ?int $chatId = null,
    ?int $messageId = null,
    ?string $inlineMessageId = null,
    ?int $timeout = null,
  ): Message {
    return $this(new SetGameScore(
      userId: $userId,
      score: $score,
      force: $force,
      disableEditMessage: $disableEditMessage,
      chatId: $chatId,
      messageId: $messageId,
      inlineMessageId: $inlineMessageId,
    ), $timeout);
  }

  /**
   * @return list<GameHighScore>
   */
  public function getGameHighScores(
    int $userId,
    ?int $chatId = null,
    ?int $messageId = null,
    ?string $inlineMessageId = null,
    ?int $timeout = null,
  ): array {
    /** @var list<GameHighScore> */
    return $this(new GetGameHighScores(
      userId: $userId,
      chatId: $chatId,
      messageId: $messageId,
      inlineMessageId: $inlineMessageId,
    ), $timeout);
  }
}
