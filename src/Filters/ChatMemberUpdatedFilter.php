<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Gruven\PhpBotGram\Types\ChatMember;
use Gruven\PhpBotGram\Types\ChatMemberAdministrator;
use Gruven\PhpBotGram\Types\ChatMemberBanned;
use Gruven\PhpBotGram\Types\ChatMemberLeft;
use Gruven\PhpBotGram\Types\ChatMemberMember;
use Gruven\PhpBotGram\Types\ChatMemberOwner;
use Gruven\PhpBotGram\Types\ChatMemberRestricted;
use Gruven\PhpBotGram\Types\ChatMemberUpdated;
use LogicException;

/**
 * Dispatcher-side filter that matches a `ChatMemberUpdated` event by
 * comparing the wire-level statuses of `old_chat_member` and `new_chat_member`.
 *
 * Port of `aiogram.filters.chat_member_updated.ChatMemberUpdatedFilter`
 * (`aiogram/filters/chat_member_updated.py:192-219`).
 *
 * # Aiogram DSL → PHP factories
 *
 * Upstream exposes a Python-only operator DSL on `_MemberStatusMarker`
 * instances:
 *
 *   KICKED | LEFT >> +MEMBER          # was kicked/left, now member
 *   JOIN_TRANSITION  = IS_NOT_MEMBER >> IS_MEMBER
 *   LEAVE_TRANSITION = ~JOIN_TRANSITION
 *   PROMOTED_TRANSITION = (MEMBER | RESTRICTED | LEFT | KICKED) >> ADMINISTRATOR
 *
 * PHP can't reuse `|` / `>>` / `~` between class instances (operator
 * overloading is limited to a handful of arithmetic methods on numeric
 * objects via `Pure\Math`). The port replaces the operator DSL with THREE
 * factory shapes matching upstream's three rule shapes:
 *
 *   1. `newStatus(array $statuses)` — constrain only the NEW status (wildcard
 *      old side). Mirrors upstream's `_MemberStatusMarker` /
 *      `_MemberStatusGroupMarker` single-axis rule. Internally stored as
 *      `new self([], $statuses)` where `oldStatuses = []` signals "match any".
 *
 *   2. `transition(array $from, array $to)` / direct constructor: explicit
 *      `oldStatuses` AND `newStatuses`. Mirrors upstream's
 *      `_MemberStatusTransition` old/new rule.
 *
 *   3. Named pre-built factories — `join()`, `leave()`, `promotion()`,
 *      `demotion()` — mirroring upstream's `JOIN_TRANSITION` /
 *      `LEAVE_TRANSITION` / `PROMOTED_TRANSITION` constants and a
 *      reverse-promotion pre-built. The `+`/`-` `is_member` modifier on
 *      `RESTRICTED` (upstream's `IS_MEMBER` includes `+RESTRICTED`,
 *      `IS_NOT_MEMBER` includes `-RESTRICTED`) is collapsed: restricted
 *      users count as members for the named factories. Callers that need
 *      the finer-grained `is_member` gate can build a transition by hand
 *      and post-filter on `oldChatMember->isMember` / `newChatMember->isMember`
 *      via a Logic AND combinator.
 *
 * # Reading `status` off `ChatMember`
 *
 * The abstract `ChatMember` parent does NOT declare a `status` property —
 * each concrete subclass (`ChatMemberOwner`, `ChatMemberAdministrator`,
 * `ChatMemberMember`, `ChatMemberRestricted`, `ChatMemberLeft`,
 * `ChatMemberBanned`) carries its own `public readonly string $status`
 * defaulted to the discriminator value. The filter resolves the wire
 * string via a static `match`-on-class helper so PHPStan level 9 stays
 * happy without a `@property` declaration on the abstract parent.
 *
 * # Return shape
 *
 * Pure `bool`. The filter contributes no kwargs to the handler — upstream's
 * `__call__` returns plain `bool` for matching/non-matching transitions.
 */
final class ChatMemberUpdatedFilter extends Filter
{
  /**
   * Singleton-status sets. The names mirror upstream's
   * `_MemberStatusMarker` instances at `chat_member_updated.py:176-181`,
   * exposed here as `list<string>` so they slot directly into the
   * constructor's `oldStatuses` / `newStatuses` parameters.
   *
   * @var list<string>
   */
  public const array CREATOR = ['creator'];

  /** @var list<string> */
  public const array ADMINISTRATOR = ['administrator'];

  /** @var list<string> */
  public const array MEMBER = ['member'];

  /** @var list<string> */
  public const array RESTRICTED = ['restricted'];

  /** @var list<string> */
  public const array LEFT = ['left'];

  /** @var list<string> */
  public const array KICKED = ['kicked'];

  /**
   * Statuses representing "currently in the chat". Mirrors upstream's
   * `IS_MEMBER = CREATOR | ADMINISTRATOR | MEMBER | +RESTRICTED` at
   * `chat_member_updated.py:183`. The `+RESTRICTED` nuance (only restricted
   * users with `is_member=True` are members) is collapsed — see class
   * docblock for the workaround.
   *
   * @var list<string>
   */
  public const array IS_MEMBER = ['creator', 'administrator', 'member', 'restricted'];

  /**
   * Statuses representing "not in the chat". Mirrors upstream's
   * `IS_NOT_MEMBER = LEFT | KICKED | -RESTRICTED` at
   * `chat_member_updated.py:185`. Same `-RESTRICTED` collapse caveat.
   *
   * @var list<string>
   */
  public const array IS_NOT_MEMBER = ['left', 'kicked'];

  /**
   * Statuses with elevated privileges. Mirrors upstream's
   * `IS_ADMIN = CREATOR | ADMINISTRATOR` at `chat_member_updated.py:184`.
   *
   * @var list<string>
   */
  public const array IS_ADMIN = ['creator', 'administrator'];

  /**
   * @param list<string> $oldStatuses Accepted statuses for `old_chat_member`.
   * @param list<string> $newStatuses Accepted statuses for `new_chat_member`.
   */
  public function __construct(
    public readonly array $oldStatuses,
    public readonly array $newStatuses,
  ) {}

  /**
   * Build a transition rule from arbitrary old/new status sets. Equivalent
   * to the constructor but reads declaratively at call sites:
   *
   *   ChatMemberUpdatedFilter::transition(
   *     from: ChatMemberUpdatedFilter::IS_NOT_MEMBER,
   *     to: ChatMemberUpdatedFilter::MEMBER,
   *   );
   *
   * @param list<string> $from
   * @param list<string> $to
   */
  public static function transition(array $from, array $to): self
  {
    return new self($from, $to);
  }

  /**
   * Constrain only the NEW status (wildcard old side). Mirrors upstream's
   * `_MemberStatusMarker` / `_MemberStatusGroupMarker` single-axis rule
   * shape — the first of upstream's three rule shapes. Passing
   * `oldStatuses = []` signals "match any old status" inside `__invoke`.
   *
   * Example — match any member who becomes an administrator, regardless of
   * their previous status:
   *
   *   ChatMemberUpdatedFilter::newStatus(ChatMemberUpdatedFilter::ADMINISTRATOR)
   *
   * @param list<string> $statuses The accepted statuses for `new_chat_member`.
   */
  public static function newStatus(array $statuses): self
  {
    return new self([], $statuses);
  }

  /**
   * Pre-built `IS_NOT_MEMBER → IS_MEMBER`. Mirrors upstream's
   * `JOIN_TRANSITION` constant at `chat_member_updated.py:187`. Matches
   * a user (re)joining the chat — left/kicked → creator/administrator/
   * member/restricted.
   */
  public static function join(): self
  {
    return new self(self::IS_NOT_MEMBER, self::IS_MEMBER);
  }

  /**
   * Pre-built `IS_MEMBER → IS_NOT_MEMBER`. Mirrors upstream's
   * `LEAVE_TRANSITION = ~JOIN_TRANSITION` at `chat_member_updated.py:188`.
   * Matches a user voluntarily leaving or being kicked/banned.
   */
  public static function leave(): self
  {
    return new self(self::IS_MEMBER, self::IS_NOT_MEMBER);
  }

  /**
   * Pre-built `MEMBER → IS_ADMIN`. Narrower than upstream's
   * `PROMOTED_TRANSITION = (MEMBER | RESTRICTED | LEFT | KICKED) >> ADMINISTRATOR`
   * (`chat_member_updated.py:189`) — we restrict the source to a plain
   * `MEMBER` so `promotion()` and `demotion()` stay symmetric. Callers
   * needing the wider upstream rule can call `transition()` directly.
   */
  public static function promotion(): self
  {
    return new self(self::MEMBER, self::IS_ADMIN);
  }

  /**
   * Pre-built `IS_ADMIN → MEMBER`. Symmetric inverse of `promotion()`. No
   * upstream constant — falls out of the marker DSL naturally
   * (`ADMINISTRATOR >> MEMBER` etc).
   */
  public static function demotion(): self
  {
    return new self(self::IS_ADMIN, self::MEMBER);
  }

  /**
   * @param mixed ...$kwargs Dispatcher kwargs bag — captured variadically so
   *                         the full bag passes through `CallableObject::prepareKwargs`.
   *                         Unused by this filter (event-only decision).
   */
  public function __invoke(object $event, mixed ...$kwargs): bool
  {
    if (!$event instanceof ChatMemberUpdated) {
      // Type guard — the dispatcher might have wired this filter onto a
      // non-`my_chat_member`/`chat_member` observer. Mirrors upstream's
      // implicit pydantic gate on the `event: ChatMemberUpdated` parameter.
      return false;
    }

    $old = self::statusOf($event->oldChatMember);
    $new = self::statusOf($event->newChatMember);

    // An empty `oldStatuses` list is the "match any" sentinel used by
    // `newStatus()` — skip the old-side check entirely when it is empty.
    // `newStatuses` always has entries (every factory provides at least one);
    // an empty `newStatuses` would match nothing, so we check unconditionally.
    $oldOk = $this->oldStatuses === [] || in_array($old, $this->oldStatuses, true);

    return $oldOk && in_array($new, $this->newStatuses, true);
  }

  /**
   * Resolve the wire-level `status` string for a `ChatMember` union value.
   *
   * The abstract `ChatMember` parent doesn't declare `status` — each
   * concrete subclass carries its own `public readonly string $status`
   * defaulted to its discriminator. PHPStan level 9 won't accept
   * `$member->status` on an abstract typed variable; the `match` over
   * concrete classes gives the static analyser the discriminator without
   * runtime reflection. Mirrors upstream's `getattr(member, "status",
   * None)` indirection at `chat_member_updated.py:89` but without the
   * `None` fallback because every constructible `ChatMember` subclass in
   * the regenerated types module declares a non-null `status`.
   */
  private static function statusOf(ChatMember $member): string
  {
    return match (true) {
      $member instanceof ChatMemberOwner => $member->status,
      $member instanceof ChatMemberAdministrator => $member->status,
      $member instanceof ChatMemberMember => $member->status,
      $member instanceof ChatMemberRestricted => $member->status,
      $member instanceof ChatMemberLeft => $member->status,
      $member instanceof ChatMemberBanned => $member->status,
      // Closed union: `ChatMemberUnion::members()` enumerates exactly the
      // six concrete subclasses above. PHPStan level 9 still flags
      // `match (true)` as non-exhaustive because the discriminant is a
      // boolean rather than an enum — throw on the impossible branch so
      // a future codegen change that adds a subclass without updating
      // this filter fails fast in tests rather than silently returning
      // an arbitrary string.
      default => throw new LogicException(sprintf(
        'Unhandled ChatMember subclass: %s. Update ChatMemberUpdatedFilter::statusOf().',
        $member::class,
      )),
    };
  }
}
