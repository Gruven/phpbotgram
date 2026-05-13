<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter;

use Error;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Utils\MagicFilter\AttrDict;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilterAsFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\RegexpMode;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Behavioural coverage for `Utils\MagicFilter\MagicFilter` — the lazy
 * predicate-chain DSL ported from the `magic_filter` PyPI package and
 * aiogram's local `as_()` extension. Each test exercises one slice of
 * the public API.
 *
 * The shape of a test is invariably:
 *   1. Build a chain via `MagicFilter::root()->…`.
 *   2. Resolve it against an object / array / scalar subject.
 *   3. Assert the returned value matches Python upstream semantics.
 */
final class MagicFilterTest extends TestCase
{
  public function testRootReturnsFreshMagicFilterInstance(): void
  {
    // `root()` is the seed of every chain — equivalent to upstream's
    // bare `F`. Two separate calls must return separate instances so
    // chain-building doesn't accidentally mutate a shared root.
    $a = MagicFilter::root();
    $b = MagicFilter::root();

    self::assertInstanceOf(MagicFilter::class, $a);
    self::assertNotSame($a, $b);
  }

  public function testEmptyChainResolvesToSubject(): void
  {
    // A chain with zero operations is the identity transform — the
    // resolver loop is a no-op and returns the original subject.
    $f = MagicFilter::root();

    self::assertSame('hello', $f->resolve('hello'));
    self::assertSame(42, $f->resolve(42));

    $subject = new stdClass();

    self::assertSame($subject, $f->resolve($subject));
  }

  public function testChainImmutabilityAttributeAccessReturnsNewInstance(): void
  {
    // The critical invariant: `__get` MUST clone the chain rather than
    // mutate `$f` in place. Otherwise `$g = $f->text` would corrupt `$f`
    // and break re-use of partial chains.
    $f = MagicFilter::root();
    $g = $f->text;

    self::assertNotSame($f, $g);
    self::assertInstanceOf(MagicFilter::class, $g);
  }

  public function testAttributeAccessReadsObjectProperty(): void
  {
    // The bread-and-butter case: walk a typed Telegram-object subject
    // and pluck a public property. The chain has a single
    // GetAttributeOperation; resolve hits the object's `id` slot.
    $subject = new Chat(id: 42, type: 'private');

    self::assertSame(42, MagicFilter::root()->id->resolve($subject));
  }

  public function testNestedAttributeAccessChain(): void
  {
    // Two GetAttributeOperations in series — `F->outer->inner`. The
    // running value advances from the root subject → outer →
    // inner.
    $outer = new stdClass();
    $outer->inner = new stdClass();
    $outer->inner->value = 'deep';

    self::assertSame('deep', MagicFilter::root()->inner->value->resolve($outer));
  }

  public function testAttributeAccessOnArraySubject(): void
  {
    // PHP-side accommodation for AttrDict-style call sites: when the
    // subject is an array, `__get` is allowed to do a key lookup.
    $subject = ['name' => 'aiogram', 'version' => '3'];

    self::assertSame('aiogram', MagicFilter::root()->name->resolve($subject));
  }

  public function testAttributeAccessOnAttrDictHybridReadsViaArrayAccess(): void
  {
    // AttrDict is both ArrayAccess and __get-overriding; the chain
    // should resolve `event_chat` against the wrapper transparently.
    $dict = new AttrDict(['event_chat' => 'private']);

    self::assertSame('private', MagicFilter::root()->event_chat->resolve($dict));
  }

  public function testMissingAttributeRaisesRejectInternallyAndChainProducesNull(): void
  {
    // Missing attribute → `RejectOperations` inside the resolver, which
    // catches it, blanks the value to null, and lets the chain finish.
    // For a single-attribute chain that means `resolve` returns null.
    $subject = new stdClass();

    self::assertNull(MagicFilter::root()->missing->resolve($subject));
  }

  public function testEqualsComparisonReturnsBoolean(): void
  {
    // `F->id == 42` → ComparatorOperation with `==` semantics. Returns
    // a boolean that the resolver propagates forward.
    $f = MagicFilter::root()->id->equals(42);

    self::assertTrue($f->resolve(new Chat(id: 42, type: 'private')));
    self::assertFalse($f->resolve(new Chat(id: 41, type: 'private')));
  }

  public function testEqAliasMatchesEquals(): void
  {
    // `eq()` is a Pythonic readability alias for `equals()`. Identical
    // behaviour — just a different name.
    $f = MagicFilter::root()->id->eq(7);

    self::assertTrue($f->resolve(new Chat(id: 7, type: 'private')));
    self::assertFalse($f->resolve(new Chat(id: 6, type: 'private')));
  }

  public function testNotEqualsAndNeAlias(): void
  {
    // Complement of equals. `ne` is the alias matching Python.
    $f = MagicFilter::root()->type->notEquals('private');

    self::assertFalse($f->resolve(new Chat(id: 1, type: 'private')));
    self::assertTrue($f->resolve(new Chat(id: 1, type: 'group')));

    $alias = MagicFilter::root()->type->ne('private');

    self::assertFalse($alias->resolve(new Chat(id: 1, type: 'private')));
    self::assertTrue($alias->resolve(new Chat(id: 1, type: 'channel')));
  }

  public function testRelationalComparators(): void
  {
    // The four ordering operators map straight to the PHP equivalents;
    // we hit all four to lock the operator table.
    $subject = new Chat(id: 10, type: 'private');

    self::assertTrue(MagicFilter::root()->id->gt(5)->resolve($subject));
    self::assertFalse(MagicFilter::root()->id->gt(15)->resolve($subject));
    self::assertTrue(MagicFilter::root()->id->gte(10)->resolve($subject));
    self::assertTrue(MagicFilter::root()->id->lt(11)->resolve($subject));
    self::assertTrue(MagicFilter::root()->id->lte(10)->resolve($subject));
  }

  public function testIsAndIsNotUseStrictIdentity(): void
  {
    // `is()` is `===`, `isNot()` is `!==`. Strict comparison catches
    // PHP loose-equal traps like `'1' == 1` (true) vs `'1' === 1`
    // (false): a string '1' is `==` 1 but is NOT `===` 1.
    self::assertTrue(MagicFilter::root()->is('1')->resolve('1'));
    self::assertFalse(MagicFilter::root()->is(1)->resolve('1'));
    self::assertTrue(MagicFilter::root()->equals(1)->resolve('1'));
    self::assertTrue(MagicFilter::root()->isNot(1)->resolve('1'));
  }

  public function testInMembership(): void
  {
    // F->status->in_(['admin', 'mod']) — value-membership test against
    // a finite haystack. Matches upstream `F.status.in_({'a','m'})`.
    $f = MagicFilter::root()->type->in_(['private', 'group']);

    self::assertTrue($f->resolve(new Chat(id: 1, type: 'private')));
    self::assertTrue($f->resolve(new Chat(id: 1, type: 'group')));
    self::assertFalse($f->resolve(new Chat(id: 1, type: 'channel')));
  }

  public function testNotInIsComplementOfIn(): void
  {
    // `not_in` rejects when the value IS in the haystack.
    $f = MagicFilter::root()->type->notIn(['private', 'group']);

    self::assertFalse($f->resolve(new Chat(id: 1, type: 'private')));
    self::assertTrue($f->resolve(new Chat(id: 1, type: 'channel')));
  }

  public function testContainsForStringHaystack(): void
  {
    // String-mode contains: substring check via `str_contains`.
    $f = MagicFilter::root()->contains('elephant');

    self::assertTrue($f->resolve('the elephant is large'));
    self::assertFalse($f->resolve('no animal here'));
  }

  public function testContainsForIterableHaystack(): void
  {
    // Iterable-mode contains: element membership.
    $f = MagicFilter::root()->contains('a');

    self::assertTrue($f->resolve(['a', 'b', 'c']));
    self::assertFalse($f->resolve(['x', 'y', 'z']));
  }

  public function testNotContainsIsComplementOfContains(): void
  {
    self::assertFalse(MagicFilter::root()->notContains('hi')->resolve('hi mom'));
    self::assertTrue(MagicFilter::root()->notContains('bye')->resolve('hi mom'));
  }

  public function testStartsWithAndEndsWith(): void
  {
    // Direct port of `F.text.startswith(...)`/`F.text.endswith(...)`.
    // Rejects on non-string subjects via the FunctionOperation reject
    // path — the chain produces `null` for those.
    self::assertTrue(MagicFilter::root()->startsWith('/')->resolve('/start'));
    self::assertFalse(MagicFilter::root()->startsWith('/')->resolve('hello'));
    self::assertTrue(MagicFilter::root()->endsWith('!')->resolve('wow!'));
    self::assertFalse(MagicFilter::root()->endsWith('!')->resolve('wow.'));
  }

  public function testCastWithFirstClassCallable(): void
  {
    // `F.cast(int)` upstream → `F->cast(intval(...))` PHP-side. The PHP
    // 8.5 first-class callable syntax produces a Closure that the
    // CastOperation invokes.
    $f = MagicFilter::root()->cast(intval(...));

    self::assertSame(42, $f->resolve('42'));
    self::assertSame(0, $f->resolve('not-a-number'));
  }

  public function testCastFailureRejectsChain(): void
  {
    // A cast Closure that throws → CastOperation catches and reraises
    // as RejectOperations → resolver short-circuits and returns null.
    $f = MagicFilter::root()->cast(static fn(): int => throw new RuntimeException('boom'));

    self::assertNull($f->resolve('anything'));
  }

  public function testFuncWithUserClosure(): void
  {
    // `F.func(callable)` — arbitrary predicate. The closure receives
    // the running value as its last positional argument (matches
    // upstream `function(*args, value, **kwargs)`).
    $f = MagicFilter::root()->func(static fn(mixed $v): bool => $v > 5);

    self::assertTrue($f->resolve(10));
    self::assertFalse($f->resolve(3));
  }

  public function testFuncWithPrependedArgs(): void
  {
    // Extra positional args come BEFORE the value to mirror upstream
    // `in_op(haystack, value)`.
    $check = static fn(int $threshold, int $value): bool => $value >= $threshold;
    $f = MagicFilter::root()->func($check, 10);

    self::assertTrue($f->resolve(20));
    self::assertFalse($f->resolve(5));
  }

  public function testLenForStringSubject(): void
  {
    // `F.text.len()` — string length via `strlen`. The result is then
    // typically piped into a comparison: `F.text.len() > 5`.
    self::assertSame(5, MagicFilter::root()->len()->resolve('hello'));
    self::assertTrue(MagicFilter::root()->len()->gt(3)->resolve('hello'));
    self::assertFalse(MagicFilter::root()->len()->gt(10)->resolve('hello'));
  }

  public function testLenForArraySubject(): void
  {
    // Polymorphic: array → `count()`.
    self::assertSame(3, MagicFilter::root()->len()->resolve(['a', 'b', 'c']));
  }

  public function testLowerUpperAndCasefold(): void
  {
    // String-case transforms. UTF-8 aware via `mb_strtolower` /
    // `mb_strtoupper` — a Russian alphabet sanity check would also pass
    // but ASCII keeps the test obvious.
    self::assertSame('hi', MagicFilter::root()->lower()->resolve('HI'));
    self::assertSame('hi', MagicFilter::root()->casefold()->resolve('HI'));
    self::assertSame('HI', MagicFilter::root()->upper()->resolve('hi'));
  }

  public function testRegexpMatchModeAnchorsAtStart(): void
  {
    // Default MATCH mode anchors with `\A` — so 'foo' matches '/foobar'
    // but '/abc/foo' does NOT (the slash prefix is not consumed).
    $f = MagicFilter::root()->regexp('foo');

    $r1 = $f->resolve('foobar');

    self::assertIsArray($r1);
    self::assertSame('foo', $r1[0]);
    self::assertNull($f->resolve('/abc/foo'));
  }

  public function testRegexpSearchModeIsUnanchored(): void
  {
    // SEARCH finds the pattern anywhere in the subject.
    $f = MagicFilter::root()->regexp('foo', RegexpMode::SEARCH);

    $r = $f->resolve('hello foo world');

    self::assertIsArray($r);
    self::assertSame('foo', $r[0]);
  }

  public function testRegexpFullmatchRequiresEntireString(): void
  {
    // FULLMATCH anchors both ends; partial matches reject.
    $f = MagicFilter::root()->regexp('foo', RegexpMode::FULLMATCH);

    self::assertNotNull($f->resolve('foo'));
    self::assertNull($f->resolve('foobar'));
  }

  public function testRegexpFindallReturnsListOfMatches(): void
  {
    // FINDALL returns the list of full-match strings.
    $f = MagicFilter::root()->regexp('\d+', RegexpMode::FINDALL);

    self::assertSame(['1', '22', '333'], $f->resolve('1 22 333'));
  }

  public function testRegexpNamedCapturesAreReturned(): void
  {
    // Named groups survive into the match array — the basis for
    // `.regexp(...).as_('match')`.
    $f = MagicFilter::root()->regexp('^/(?<cmd>\w+)');
    $result = $f->resolve('/start');

    self::assertIsArray($result);
    self::assertSame('/start', $result[0]);
    self::assertSame('start', $result['cmd']);
  }

  public function testAndComposition(): void
  {
    // `$f->and_($g)` — both must hold. The right side is resolved
    // against the same subject as the left.
    $f = MagicFilter::root()->id->equals(7);
    $g = MagicFilter::root()->type->equals('private');
    $combined = $f->and_($g);

    self::assertTrue($combined->resolve(new Chat(id: 7, type: 'private')));
    self::assertFalse($combined->resolve(new Chat(id: 7, type: 'group')));
    self::assertFalse($combined->resolve(new Chat(id: 8, type: 'private')));
  }

  public function testOrComposition(): void
  {
    // Either branch can accept; the OR is "important" so a left-side
    // rejection still lets the right side vote.
    $f = MagicFilter::root()->id->equals(7);
    $g = MagicFilter::root()->type->equals('group');
    $combined = $f->or_($g);

    self::assertTrue($combined->resolve(new Chat(id: 7, type: 'private')));
    self::assertTrue($combined->resolve(new Chat(id: 99, type: 'group')));
    self::assertFalse($combined->resolve(new Chat(id: 99, type: 'channel')));
  }

  public function testNotInversion(): void
  {
    // `$f->not_()` flips the verdict. The result coerces the running
    // value to bool before flipping.
    $f = MagicFilter::root()->id->equals(7)->not_();

    self::assertFalse($f->resolve(new Chat(id: 7, type: 'private')));
    self::assertTrue($f->resolve(new Chat(id: 8, type: 'private')));
  }

  public function testNotIsImportantAndRescuesRejectedChain(): void
  {
    // `~F.text` should be true when `text` is missing — the rejection
    // (null value) inverts to `!null === true`. This is the canonical
    // `important` flag use case.
    $f = MagicFilter::root()->missing->not_();

    self::assertTrue($f->resolve(new stdClass()));
  }

  public function testDoubleNotFoldsBackToOriginal(): void
  {
    // `~~F` ≡ `F` upstream; we replicate via `excludeLast` when the
    // tail is an ImportantFunctionOperation matching the "no-arg
    // negation" shape.
    $f = MagicFilter::root()->id->equals(7);
    $folded = $f->not_()->not_();

    self::assertTrue($folded->resolve(new Chat(id: 7, type: 'private')));
    self::assertFalse($folded->resolve(new Chat(id: 8, type: 'private')));
  }

  public function testNegateIsAliasForNot(): void
  {
    // Readability alias — same semantics as `not_()`.
    $f = MagicFilter::root()->id->equals(7)->negate();

    self::assertFalse($f->resolve(new Chat(id: 7, type: 'private')));
    self::assertTrue($f->resolve(new Chat(id: 8, type: 'private')));
  }

  public function testXorComposition(): void
  {
    // Exactly one side accepts.
    $f = MagicFilter::root()->id->equals(7)->xor_(MagicFilter::root()->type->equals('private'));

    self::assertFalse($f->resolve(new Chat(id: 7, type: 'private')));
    self::assertTrue($f->resolve(new Chat(id: 7, type: 'group')));
    self::assertTrue($f->resolve(new Chat(id: 8, type: 'private')));
    self::assertFalse($f->resolve(new Chat(id: 8, type: 'group')));
  }

  public function testAsKwargPackagesFinalValueAsArray(): void
  {
    // `as_('name')` is the terminal that wraps the chain's value as
    // a `{name: value}` kwarg map.
    $f = MagicFilter::root()->id->as_('chatId');

    self::assertSame(['chatId' => 42], $f->resolve(new Chat(id: 42, type: 'private')));
  }

  public function testAsKwargWithFalsyButNonNullValueStillAccepts(): void
  {
    // Spec: `as_` rejects ONLY on null / empty-iterable. `false`/`0`/`''`
    // are accepting payloads — matches upstream's
    // `AsFilterResultOperation` semantics.
    $f = MagicFilter::root()->startsWith('hi')->as_('matched');
    // 'no' does not start with 'hi' → chain value is `false`, not null
    // → `as_` packages it as `['matched' => false]`.
    self::assertSame(['matched' => false], $f->resolve('no'));
  }

  public function testAsKwargWithNullValueRejects(): void
  {
    // Missing attribute → null → `as_` returns null (rejection signal).
    $f = MagicFilter::root()->missing->as_('value');

    self::assertNull($f->resolve(new stdClass()));
  }

  public function testAsFilterBridgeReturnsTrueForTruthyValue(): void
  {
    // `asFilter()` wraps the chain in a Filter. A truthy resolve →
    // `true`. A rejection → `false`.
    $filter = MagicFilter::root()->id->equals(7)->asFilter();

    self::assertInstanceOf(MagicFilterAsFilter::class, $filter);
    self::assertTrue($filter(new Chat(id: 7, type: 'private')));
    self::assertFalse($filter(new Chat(id: 8, type: 'private')));
  }

  public function testAsFilterBridgePropagatesKwargArray(): void
  {
    // `as_(...)->asFilter()` injects the kwarg map verbatim; the
    // dispatcher will then merge it into handler args.
    $filter = MagicFilter::root()->id->as_('chatId')->asFilter();

    self::assertSame(
      ['chatId' => 42],
      $filter(new Chat(id: 42, type: 'private')),
    );
  }

  public function testAsFilterBridgeCollapsesNullToFalse(): void
  {
    // A null final value from a rejected chain becomes a Filter `false`.
    $filter = MagicFilter::root()->missing->asFilter();

    self::assertFalse($filter(new Chat(id: 1, type: 'private')));
  }

  public function testMethodCallOnObjectViaUnknownName(): void
  {
    // PHP `__call` fires for any non-declared method name — append
    // `GetAttributeOperation + CallOperation` so `F->text->myMethod()`
    // resolves to `$value->myMethod()` when the running value supports it.
    $subject = new class {
      public string $name = 'callable-subject';

      public function shout(): string
      {
        return strtoupper($this->name);
      }
    };

    self::assertSame('CALLABLE-SUBJECT', MagicFilter::root()->shout()->resolve($subject));
  }

  public function testMethodCallWithArgumentsThreadsArgsToCallable(): void
  {
    // The CallOperation forwards args + kwargs to the value the
    // preceding GetAttributeOperation produced.
    $subject = new class {
      public function add(int $a, int $b): int
      {
        return $a + $b;
      }
    };

    self::assertSame(7, MagicFilter::root()->add(3, 4)->resolve($subject));
  }

  public function testItemSubscriptOnArraySubject(): void
  {
    // `$f->item('key')` is the PHP analogue of `F['key']`. Used for
    // subjects without first-class properties — middleware data dicts,
    // raw maps, etc.
    self::assertSame(
      'aiogram',
      MagicFilter::root()->item('name')->resolve(['name' => 'aiogram']),
    );
  }

  public function testItemSubscriptMissingKeyRejects(): void
  {
    // Missing key → RejectOperations → resolver collapses to null.
    self::assertNull(MagicFilter::root()->item('missing')->resolve(['name' => 'x']));
  }

  public function testItemWithMagicFilterIsSelector(): void
  {
    // `$f->item($inner)` where $inner is a MagicFilter becomes a
    // SelectorOperation — passes the value through if the inner
    // accepts.
    $f = MagicFilter::root()->item(MagicFilter::root()->gt(5));

    self::assertSame(10, $f->resolve(10));
    self::assertNull($f->resolve(3));
  }

  public function testWildcardAllAcceptsWhenEveryItemSatisfiesRemainingChain(): void
  {
    // `$f->all()->gt(5)` accepts when every entry of an iterable
    // satisfies `> 5`. Mirrors upstream `F[:].gt(5)`.
    $f = MagicFilter::root()->all()->gt(5);

    self::assertTrue($f->resolve([10, 20, 30]));
    self::assertFalse($f->resolve([10, 1, 30]));
  }

  public function testWildcardAnyAcceptsWhenAnyItemSatisfiesRemainingChain(): void
  {
    // `$f->any()->gt(5)` accepts when at least one entry satisfies
    // the remaining chain.
    $f = MagicFilter::root()->any()->gt(5);

    self::assertTrue($f->resolve([1, 2, 10]));
    self::assertFalse($f->resolve([1, 2, 3]));
  }

  public function testExtractFiltersIterableSubjectByInnerChain(): void
  {
    // `extract` is "select where inner accepts" but RETAINS the matched
    // elements rather than passing the original list through. Upstream
    // returns the matched-list verbatim.
    $f = MagicFilter::root()->extract(MagicFilter::root()->gt(5));

    self::assertSame([10, 20], $f->resolve([1, 10, 3, 20, 5]));
  }

  public function testResolveOnNonMagicValueReturnsItVerbatim(): void
  {
    // The Helper resolver short-circuits on non-MagicFilter values.
    // Use a comparison whose right operand is a literal int — it stays
    // intact through resolution.
    self::assertTrue(MagicFilter::root()->equals(42)->resolve(42));
  }

  public function testComparatorRightOperandCanBeMagicFilter(): void
  {
    // `F->id == F->reply->fromUser->id` upstream → resolve the right-
    // hand chain against the root subject. We model that with a
    // synthetic nested subject.
    $subject = new stdClass();
    $subject->a = 7;
    $subject->b = 7;

    $f = MagicFilter::root()->a->equals(MagicFilter::root()->b);

    self::assertTrue($f->resolve($subject));

    $subject->b = 8;

    self::assertFalse($f->resolve($subject));
  }

  public function testAttributeAccessRejectsUnderscorePrefixedNames(): void
  {
    // PHP-internal names (those starting with `_`) MUST raise rather
    // than become chain operations — they would clash with PHP magic
    // dispatch internals (`__call`, `__get`, etc.) and probably surface
    // weird behaviour.
    $this->expectException(Error::class);
    // @phpstan-ignore-next-line — intentional probe of the protected name.
    MagicFilter::root()->_private;
  }

  public function testEqualsAgainstFalseAcceptsButProducesFalseValue(): void
  {
    // Subtle: `equals(false)` returns a comparison whose value IS
    // `false` when the subject is `false`. The resolver treats the
    // boolean as a regular value; `asFilter()` would coerce to a Filter
    // false — but the chain value pre-asFilter is `true` when the
    // comparison holds.
    $f = MagicFilter::root()->equals(false);

    self::assertTrue($f->resolve(false));
    self::assertFalse($f->resolve(true));
  }

  public function testAsFilterIsCompatibleWithFilterAbstract(): void
  {
    // The bridge IS-A Filter, so it composes via Filter::all/any/invertOf
    // the same as any other dispatcher predicate.
    $filter = MagicFilter::root()->id->equals(7)->asFilter();
    self::assertInstanceOf(Filter::class, $filter);

    $inverted = Filter::invertOf($filter);
    // Matching subject → inner accepts → invert rejects.
    self::assertFalse($inverted(new Chat(id: 7, type: 'private')));
    // Non-matching subject → inner rejects → invert accepts.
    self::assertTrue($inverted(new Chat(id: 8, type: 'private')));
  }
}
