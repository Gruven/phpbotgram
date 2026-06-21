<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents a type of content in message
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum ContentType: string
{
  case Unknown = 'unknown';
  case Any = 'any';
  case Text = 'text';
  case RichMessage = 'rich_message';
  case Animation = 'animation';
  case Audio = 'audio';
  case Document = 'document';
  case LivePhoto = 'live_photo';
  case PaidMedia = 'paid_media';
  case Photo = 'photo';
  case Sticker = 'sticker';
  case Story = 'story';
  case Video = 'video';
  case VideoNote = 'video_note';
  case Voice = 'voice';
  case Checklist = 'checklist';
  case Contact = 'contact';
  case Dice = 'dice';
  case Game = 'game';
  case Poll = 'poll';
  case Venue = 'venue';
  case Location = 'location';
  case NewChatMembers = 'new_chat_members';
  case LeftChatMember = 'left_chat_member';
  case ChatOwnerLeft = 'chat_owner_left';
  case ChatOwnerChanged = 'chat_owner_changed';
  case NewChatTitle = 'new_chat_title';
  case NewChatPhoto = 'new_chat_photo';
  case DeleteChatPhoto = 'delete_chat_photo';
  case GroupChatCreated = 'group_chat_created';
  case SupergroupChatCreated = 'supergroup_chat_created';
  case ChannelChatCreated = 'channel_chat_created';
  case MessageAutoDeleteTimerChanged = 'message_auto_delete_timer_changed';
  case MigrateToChatId = 'migrate_to_chat_id';
  case MigrateFromChatId = 'migrate_from_chat_id';
  case PinnedMessage = 'pinned_message';
  case Invoice = 'invoice';
  case SuccessfulPayment = 'successful_payment';
  case RefundedPayment = 'refunded_payment';
  case UsersShared = 'users_shared';
  case ChatShared = 'chat_shared';
  case Gift = 'gift';
  case UniqueGift = 'unique_gift';
  case GiftUpgradeSent = 'gift_upgrade_sent';
  case ConnectedWebsite = 'connected_website';
  case WriteAccessAllowed = 'write_access_allowed';
  case PassportData = 'passport_data';
  case ProximityAlertTriggered = 'proximity_alert_triggered';
  case BoostAdded = 'boost_added';
  case ChatBackgroundSet = 'chat_background_set';
  case ChecklistTasksDone = 'checklist_tasks_done';
  case ChecklistTasksAdded = 'checklist_tasks_added';
  case DirectMessagePriceChanged = 'direct_message_price_changed';
  case ForumTopicCreated = 'forum_topic_created';
  case ForumTopicEdited = 'forum_topic_edited';
  case ForumTopicClosed = 'forum_topic_closed';
  case ForumTopicReopened = 'forum_topic_reopened';
  case GeneralForumTopicHidden = 'general_forum_topic_hidden';
  case GeneralForumTopicUnhidden = 'general_forum_topic_unhidden';
  case GiveawayCreated = 'giveaway_created';
  case Giveaway = 'giveaway';
  case GiveawayWinners = 'giveaway_winners';
  case GiveawayCompleted = 'giveaway_completed';
  case ManagedBotCreated = 'managed_bot_created';
  case PaidMessagePriceChanged = 'paid_message_price_changed';
  case PollOptionAdded = 'poll_option_added';
  case PollOptionDeleted = 'poll_option_deleted';
  case SuggestedPostApproved = 'suggested_post_approved';
  case SuggestedPostApprovalFailed = 'suggested_post_approval_failed';
  case SuggestedPostDeclined = 'suggested_post_declined';
  case SuggestedPostPaid = 'suggested_post_paid';
  case SuggestedPostRefunded = 'suggested_post_refunded';
  case VideoChatScheduled = 'video_chat_scheduled';
  case VideoChatStarted = 'video_chat_started';
  case VideoChatEnded = 'video_chat_ended';
  case VideoChatParticipantsInvited = 'video_chat_participants_invited';
  case WebAppData = 'web_app_data';
}
