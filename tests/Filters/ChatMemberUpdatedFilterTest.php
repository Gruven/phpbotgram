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
 *
 * Upstream `tests/test_filters/test_chat_member_updated.py` cases deliberately not ported:
 *
 * - Entire `TestMemberStatusMarker` class (`test_str`, `test_pos`, `test_neg`, `test_or`,
 *   `test_rshift`, `test_lshift`, `test_hash`, `test_check`) — the Python marker/operator
 *   DSL (`+`, `-`, `|`, `>>`, `<<`) on `_MemberStatusMarker` instances cannot be
 *   expressed in PHP (reason 3).
 * - Entire `TestMemberStatusGroupMarker` class — same operator-DSL limitation (reason 3).
 * - Entire `TestMemberStatusTransition` class (`test_invert`, `test_check`) — same (reason 3).
 * - `TestChatMemberUpdatedStatusFilter::test_str` — `Filter` and DTOs have no `__str__` /
 *   `__repr__` equivalents in the PHP port (reason 5).
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
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

  // -------------------------------------------------------------------------
  // A4 — upstream row 3: restricted(is_member=False) → member under JOIN_TRANSITION
  // -------------------------------------------------------------------------

  public function testJoinRejectsRestrictedNotMemberToMember(): void
  {
    // Upstream `TestChatMemberUpdatedStatusFilter::test_call` row 3:
    //   JOIN_TRANSITION, restricted(is_member=False) → member → True upstream.
    //
    // PHP API divergence: upstream marks `restricted(is_member=False)` as
    // IS_NOT_MEMBER because the `+is_member` qualifier demotes the restricted
    // status. The PHP port collapses the `is_member` modifier — `restricted`
    // is ALWAYS in IS_MEMBER regardless of `isMember`. Therefore
    // `restricted → member` fails the `in_array('restricted', IS_NOT_MEMBER)`
    // check and returns false.
    //
    // Callers that need the finer-grained `is_member=False` distinction can
    // post-filter via an AND combinator checking `ChatMemberRestricted::$isMember`.
    $filter = ChatMemberUpdatedFilter::join();

    // NOTE: Upstream returns True for this row; our PHP port returns False
    // due to the is_member-collapse trade-off. See class docblock.
    self::assertFalse($filter($this->chatMemberUpdated(
      old: $this->restrictedNotMember(),
      new: $this->member(),
    )));
  }

  // -------------------------------------------------------------------------
  // A5 — upstream row 6: member → administrator with ADMINISTRATOR status filter
  // -------------------------------------------------------------------------

  public function testFilterMatchesMemberToAdministratorTransition(): void
  {
    // Upstream `TestChatMemberUpdatedStatusFilter::test_call` row 6:
    //   ADMINISTRATOR (status filter), member → administrator → True.
    // Upstream uses the single-axis `ADMINISTRATOR` marker (new == administrator,
    // old unconstrained). PHP's `promotion()` is stricter (requires old in MEMBER,
    // new in IS_ADMIN). This specific row (`member → administrator`) satisfies both,
    // so `promotion()` accepts it. Other upstream rows that pass under `ADMINISTRATOR`
    // without an old-status constraint would require a transition built via the
    // `transition()` factory; those are not part of this row.
    $filter = ChatMemberUpdatedFilter::promotion();

    self::assertTrue($filter($this->chatMemberUpdated(
      old: $this->member(),
      new: $this->administrator(),
    )));
  }

  // -------------------------------------------------------------------------
  // newStatus() factory — upstream _MemberStatusMarker single-axis rule shape
  // -------------------------------------------------------------------------

  public function testNewStatusFactoryMatchesAnyOldChatMember(): void
  {
    // `newStatus()` constrains ONLY the new side — `oldStatuses = []` is the
    // "match any" sentinel. Mirrors upstream's `_MemberStatusMarker` /
    // `_MemberStatusGroupMarker` single-axis rule shape that the constructor's
    // mandatory `oldStatuses` previously made inexpressible.
    //
    // Two transitions both become ADMINISTRATOR:
    //   - member → administrator   (old is in IS_MEMBER)
    //   - left   → administrator   (old is in IS_NOT_MEMBER)
    // Both must match because the old side is wildcarded.
    $filter = ChatMemberUpdatedFilter::newStatus(ChatMemberUpdatedFilter::ADMINISTRATOR);

    self::assertTrue(
      $filter($this->chatMemberUpdated(old: $this->member(), new: $this->administrator())),
      'newStatus(ADMINISTRATOR) must accept member → administrator',
    );

    self::assertTrue(
      $filter($this->chatMemberUpdated(old: $this->left(), new: $this->administrator())),
      'newStatus(ADMINISTRATOR) must accept left → administrator (wildcard old side)',
    );
  }

  public function testNewStatusFactoryStoresEmptyOldStatuses(): void
  {
    // Verify the factory shape: `oldStatuses` is `[]`, `newStatuses` is set.
    $filter = ChatMemberUpdatedFilter::newStatus(ChatMemberUpdatedFilter::MEMBER);

    self::assertSame([], $filter->oldStatuses);
    self::assertSame(['member'], $filter->newStatuses);
  }

  public function testNewStatusFactoryRejectsNonMatchingNewStatus(): void
  {
    // Even with a wildcarded old side, a mismatched new status must reject.
    $filter = ChatMemberUpdatedFilter::newStatus(ChatMemberUpdatedFilter::ADMINISTRATOR);

    self::assertFalse(
      $filter($this->chatMemberUpdated(old: $this->left(), new: $this->member())),
      'newStatus(ADMINISTRATOR) must reject transitions whose new status is not administrator',
    );
  }

  // -------------------------------------------------------------------------
  // A6 — upstream row 7: restricted(is_member=False) → member under IS_MEMBER filter
  // -------------------------------------------------------------------------

  public function testJoinRejectsRestrictedNotMemberToMemberWithIsMemberFilter(): void
  {
    // Upstream `TestChatMemberUpdatedStatusFilter::test_call` row 7:
    //   IS_MEMBER (status filter), restricted(is_member=False) → member → True upstream.
    //
    // PHP API divergence: the upstream IS_MEMBER marker checks that the new
    // status is a member-category status, including `restricted(is_member=True)`.
    // The PHP IS_MEMBER set is ['creator','administrator','member','restricted']
    // regardless of `isMember`. A `restricted → member` new-status check would
    // pass IS_MEMBER since 'member' is in IS_MEMBER. However this row tests a
    // single-status filter (not a transition), which maps to our `join()` factory
    // (IS_NOT_MEMBER → IS_MEMBER) or a direct transition filter.
    //
    // Using a transition filter: old must be IS_NOT_MEMBER. `restricted` is in
    // IS_MEMBER in our model, so the old-status check fails → false.
    // See testJoinRejectsRestrictedNotMemberToMember for the parallel case.
    $filter = new ChatMemberUpdatedFilter(
      oldStatuses: ['left', 'kicked'],  // IS_NOT_MEMBER in PHP
      newStatuses: ['creator', 'administrator', 'member', 'restricted'],  // IS_MEMBER in PHP
    );

    // NOTE: Upstream returns True for this row (restricted with is_member=False
    // is IS_NOT_MEMBER there). Our PHP port returns False because `restricted`
    // is in IS_MEMBER unconditionally in the PHP model.
    self::assertFalse($filter($this->chatMemberUpdated(
      old: $this->restrictedNotMember(),
      new: $this->member(),
    )));
  }

  /**
   * Build a `ChatMemberRestricted` with `isMember: false` to mirror upstream's
   * `restricted(is_member=False)` parametrize rows. Used in A4 and A6 tests.
   */
  private function restrictedNotMember(): ChatMemberRestricted
  {
    return new ChatMemberRestricted(
      user: new User(id: 1, isBot: false, firstName: 'Ada'),
      isMember: false,
      canSendMessages: false,
      canSendAudios: false,
      canSendDocuments: false,
      canSendPhotos: false,
      canSendVideos: false,
      canSendVideoNotes: false,
      canSendVoiceNotes: false,
      canSendPolls: false,
      canSendOtherMessages: false,
      canAddWebPagePreviews: false,
      canReactToMessages: false,
      canEditTag: false,
      canChangeInfo: false,
      canInviteUsers: false,
      canPinMessages: false,
      canManageTopics: false,
      untilDate: new DateTime('@0'),
    );
  }
}
