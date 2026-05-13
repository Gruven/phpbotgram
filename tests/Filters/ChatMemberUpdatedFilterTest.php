<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\ChatMemberUpdatedFilter;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\ChatMember;
use Gruven\PhpBotGram\Types\ChatMemberAdministrator;
use Gruven\PhpBotGram\Types\ChatMemberBanned;
use Gruven\PhpBotGram\Types\ChatMemberLeft;
use Gruven\PhpBotGram\Types\ChatMemberMember;
use Gruven\PhpBotGram\Types\ChatMemberOwner;
use Gruven\PhpBotGram\Types\ChatMemberRestricted;
use Gruven\PhpBotGram\Types\ChatMemberUpdated;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `ChatMemberUpdatedFilter` — the old/new chat-member status
 * transition matcher. Port of
 * `aiogram.filters.chat_member_updated.ChatMemberUpdatedFilter`
 * (`aiogram/filters/chat_member_updated.py:192-219`).
 *
 * Aiogram's marker-and-operator DSL (`KICKED | LEFT >> +MEMBER`) doesn't
 * survive into PHP — PHP can't reuse `|` / `>>` between class instances.
 * The port keeps the same semantics via named factories and arrays of
 * wire-level status strings, plus pre-built `join()` / `leave()` /
 * `promotion()` / `demotion()` factories that mirror the upstream
 * `JOIN_TRANSITION` / `LEAVE_TRANSITION` / `PROMOTED_TRANSITION` constants.
 *
 * Spec note: every concrete `ChatMember` subclass exposes its wire-level
 * `status` via a `public readonly string $status` property defaulted to
 * its discriminator (`'creator'`, `'administrator'`, `'member'`,
 * `'restricted'`, `'left'`, `'kicked'`). The abstract `ChatMember` parent
 * does NOT declare `status` — the filter therefore reads it via a static
 * `match`-on-class helper rather than a parent accessor.
 */
final class ChatMemberUpdatedFilterTest extends TestCase
{
  public function testIsAFilterSubclass(): void
  {
    // Smoke check — dispatcher cascading + Logic combinators require every
    // concrete filter to extend `Filter`.
    self::assertInstanceOf(Filter::class, ChatMemberUpdatedFilter::join());
  }

  public function testConstructorStoresStatusSets(): void
  {
    // Direct construction is part of the public surface (tests, Logic
    // combinator wiring, ad-hoc transition rules the factories don't cover).
    // The readonly fields must round-trip what the caller passed.
    $filter = new ChatMemberUpdatedFilter(['left'], ['member']);

    self::assertSame(['left'], $filter->oldStatuses);
    self::assertSame(['member'], $filter->newStatuses);
  }

  public function testMatchesLeftToMemberTransition(): void
  {
    // Direct match: old=left, new=member, rule=left → member. Returns true.
    // Mirrors upstream's `return old in old_statuses and new in new_statuses`.
    $filter = new ChatMemberUpdatedFilter(['left'], ['member']);

    self::assertTrue($filter($this->chatMemberUpdated(
      old: $this->left(),
      new: $this->member(),
    )));
  }

  public function testRejectsReverseTransition(): void
  {
    // The filter is directional: member → left is NOT a left → member
    // transition. Returns false. Locks down that we don't accidentally
    // collapse old/new into a symmetric set check.
    $filter = new ChatMemberUpdatedFilter(['left'], ['member']);

    self::assertFalse($filter($this->chatMemberUpdated(
      old: $this->member(),
      new: $this->left(),
    )));
  }

  public function testRejectsNonChatMemberUpdatedEvent(): void
  {
    // Dispatcher might wire the filter onto a non-`my_chat_member` observer
    // by mistake; the type guard rejects rather than crashing. Mirrors
    // upstream's `event: ChatMemberUpdated` signature — pydantic raises if
    // the dispatcher passed something else, the PHP port reports `false`.
    $filter = ChatMemberUpdatedFilter::join();
    $message = new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
    );

    self::assertFalse($filter($message));
  }

  public function testTransitionFactoryBuildsFilter(): void
  {
    // `::transition($from, $to)` is the explicit factory for arbitrary
    // status sets. Equivalent to the constructor but reads more declaratively
    // at call sites: `ChatMemberUpdatedFilter::transition(['left'], ['member'])`.
    $filter = ChatMemberUpdatedFilter::transition(['left', 'kicked'], ['member']);

    self::assertSame(['left', 'kicked'], $filter->oldStatuses);
    self::assertSame(['member'], $filter->newStatuses);
  }

  public function testJoinMatchesKickedToAdministrator(): void
  {
    // `join()` = IS_NOT_MEMBER → IS_MEMBER. Admin/owner count as members in
    // chat per upstream's IS_MEMBER set (creator/administrator/member/restricted).
    // So a banned user being promoted (kicked → administrator) satisfies the
    // join transition. Mirrors `JOIN_TRANSITION = IS_NOT_MEMBER >> IS_MEMBER`.
    $filter = ChatMemberUpdatedFilter::join();

    self::assertTrue($filter($this->chatMemberUpdated(
      old: $this->kicked(),
      new: $this->administrator(),
    )));
  }

  public function testJoinRejectsMemberToKicked(): void
  {
    // member → kicked is a leave transition, not a join. The directional
    // semantics keep `join()` and `leave()` non-overlapping. Mirrors
    // `LEAVE_TRANSITION = ~JOIN_TRANSITION` upstream.
    $filter = ChatMemberUpdatedFilter::join();

    self::assertFalse($filter($this->chatMemberUpdated(
      old: $this->member(),
      new: $this->kicked(),
    )));
  }

  public function testLeaveMatchesMemberToLeft(): void
  {
    // `leave()` = IS_MEMBER → IS_NOT_MEMBER. Member voluntarily leaving
    // (member → left) is the canonical case. Equivalent to upstream's
    // `~JOIN_TRANSITION`.
    $filter = ChatMemberUpdatedFilter::leave();

    self::assertTrue($filter($this->chatMemberUpdated(
      old: $this->member(),
      new: $this->left(),
    )));
  }

  public function testPromotionMatchesMemberToAdministrator(): void
  {
    // `promotion()` = MEMBER → IS_ADMIN. Regular member promoted to admin
    // is the standard case. The pre-built sits between upstream's hand-rolled
    // `(MEMBER | RESTRICTED | LEFT | KICKED) >> ADMINISTRATOR` and a narrower
    // MEMBER-only rule; we narrow to MEMBER → admin/owner to keep the named
    // factory predictable. Documented in the class docblock.
    $filter = ChatMemberUpdatedFilter::promotion();

    self::assertTrue($filter($this->chatMemberUpdated(
      old: $this->member(),
      new: $this->administrator(),
    )));
  }

  public function testPromotionRejectsAdministratorToMember(): void
  {
    // administrator → member is a demotion, not a promotion. Keeps the two
    // pre-built factories non-overlapping.
    $filter = ChatMemberUpdatedFilter::promotion();

    self::assertFalse($filter($this->chatMemberUpdated(
      old: $this->administrator(),
      new: $this->member(),
    )));
  }

  public function testDemotionMatchesAdministratorToMember(): void
  {
    // `demotion()` = IS_ADMIN → MEMBER. Owner/admin reverting to a plain
    // member is the inverse of `promotion()`. No upstream constant for this
    // — it falls out of the marker DSL naturally.
    $filter = ChatMemberUpdatedFilter::demotion();

    self::assertTrue($filter($this->chatMemberUpdated(
      old: $this->administrator(),
      new: $this->member(),
    )));
  }

  public function testOwnerCountsAsAdminForDemotion(): void
  {
    // IS_ADMIN includes creator + administrator. An owner stepping down to
    // member (rare but possible via ownership transfer) is still a demotion.
    // Locks down that `demotion()` honours both admin variants.
    $filter = ChatMemberUpdatedFilter::demotion();

    self::assertTrue($filter($this->chatMemberUpdated(
      old: $this->owner(),
      new: $this->member(),
    )));
  }

  /**
   * Build a minimal `ChatMemberUpdated` event for filter exercise. The
   * `chat`, `fromUser`, `date` fields are required by the constructor but
   * irrelevant to status-transition matching; we use stable defaults so each
   * test focuses on the old/new pair.
   */
  private function chatMemberUpdated(ChatMember $old, ChatMember $new): ChatMemberUpdated
  {
    $chat = new Chat(id: -100, type: 'supergroup');
    $user = new User(id: 1, isBot: false, firstName: 'Ada');

    return new ChatMemberUpdated(
      chat: $chat,
      fromUser: $user,
      date: new DateTime('@0'),
      oldChatMember: $old,
      newChatMember: $new,
    );
  }

  private function owner(): ChatMemberOwner
  {
    return new ChatMemberOwner(
      user: new User(id: 1, isBot: false, firstName: 'Ada'),
      isAnonymous: false,
    );
  }

  private function administrator(): ChatMemberAdministrator
  {
    return new ChatMemberAdministrator(
      user: new User(id: 1, isBot: false, firstName: 'Ada'),
      canBeEdited: true,
      isAnonymous: false,
      canManageChat: true,
      canDeleteMessages: true,
      canManageVideoChats: true,
      canRestrictMembers: true,
      canPromoteMembers: false,
      canChangeInfo: false,
      canInviteUsers: true,
      canPostStories: false,
      canEditStories: false,
      canDeleteStories: false,
    );
  }

  private function member(): ChatMemberMember
  {
    return new ChatMemberMember(
      user: new User(id: 1, isBot: false, firstName: 'Ada'),
    );
  }

  private function restricted(): ChatMemberRestricted
  {
    return new ChatMemberRestricted(
      user: new User(id: 1, isBot: false, firstName: 'Ada'),
      isMember: true,
      canSendMessages: true,
      canSendAudios: true,
      canSendDocuments: true,
      canSendPhotos: true,
      canSendVideos: true,
      canSendVideoNotes: true,
      canSendVoiceNotes: true,
      canSendPolls: true,
      canSendOtherMessages: true,
      canAddWebPagePreviews: true,
      canReactToMessages: true,
      canEditTag: false,
      canChangeInfo: false,
      canInviteUsers: true,
      canPinMessages: false,
      canManageTopics: false,
      untilDate: new DateTime('@0'),
    );
  }

  private function left(): ChatMemberLeft
  {
    return new ChatMemberLeft(
      user: new User(id: 1, isBot: false, firstName: 'Ada'),
    );
  }

  private function kicked(): ChatMemberBanned
  {
    return new ChatMemberBanned(
      user: new User(id: 1, isBot: false, firstName: 'Ada'),
      untilDate: new DateTime('@0'),
    );
  }

  public function testJoinRejectsRestrictedMemberToMember(): void
  {
    // Upstream `TestChatMemberUpdatedStatusFilter::test_call` row 2:
    //   JOIN_TRANSITION, restricted(is_member=True) → member → False.
    // Reasoning: a `restricted` user with `is_member=True` was ALREADY a
    // chat member — the transition is NOT a join (it is a status change
    // within IS_MEMBER). Our PHP implementation collapses the `+is_member`
    // modifier: `restricted` is always in IS_MEMBER, so `restricted → member`
    // fails the `in_array($old, IS_NOT_MEMBER)` check and returns false.
    // The collapsed-is_member trade-off is documented in the class docblock.
    $filter = ChatMemberUpdatedFilter::join();

    self::assertFalse($filter($this->chatMemberUpdated(
      old: $this->restricted(),
      new: $this->member(),
    )));
  }

  public function testJoinRejectsMemberToRestricted(): void
  {
    // Upstream `TestChatMemberUpdatedStatusFilter::test_call` row 4:
    //   JOIN_TRANSITION, member → restricted(is_member=False) → False.
    // `member` is in IS_MEMBER, which is NOT in IS_NOT_MEMBER, so
    // `in_array('member', IS_NOT_MEMBER)` is false — join() requires the
    // old status to be IS_NOT_MEMBER. The transition fails the old-status
    // check immediately.
    $filter = ChatMemberUpdatedFilter::join();

    self::assertFalse($filter($this->chatMemberUpdated(
      old: $this->member(),
      new: $this->restricted(),
    )));
  }

  public function testLeaveRejectsMemberToRestricted(): void
  {
    // Upstream `TestChatMemberUpdatedStatusFilter::test_call` row 5:
    //   LEAVE_TRANSITION, member → restricted(is_member=False) → True upstream.
    // PHP API divergence: our `leave()` = IS_MEMBER → IS_NOT_MEMBER. Because
    // the PHP port collapses the `is_member` modifier on `restricted`, the
    // `restricted` status is in IS_MEMBER (not IS_NOT_MEMBER). Therefore
    // `member → restricted` fails the new-status check and returns false.
    // Callers needing the finer-grained `+is_member` gate can post-filter
    // on `ChatMemberRestricted::$isMember` via a Logic AND combinator.
    $filter = ChatMemberUpdatedFilter::leave();

    // NOTE: This intentionally diverges from upstream (upstream: True).
    // The PHP port returns false because `restricted` is in IS_MEMBER, not
    // IS_NOT_MEMBER. See class docblock for the workaround.
    self::assertFalse($filter($this->chatMemberUpdated(
      old: $this->member(),
      new: $this->restricted(),
    )));
  }

  public function testRestrictedFixtureCarriesRestrictedStatus(): void
  {
    // Sanity-check the helper — restricted is a legitimate IS_MEMBER state
    // upstream (a restricted user is still in the chat). Locks down that
    // the test fixture doesn't drift if codegen changes ChatMemberRestricted's
    // signature.
    self::assertSame('restricted', $this->restricted()->status);
  }
}
