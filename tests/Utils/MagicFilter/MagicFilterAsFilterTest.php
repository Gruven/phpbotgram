<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilterAsFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `MagicFilterAsFilter` — the bridge that converts a
 * `MagicFilter` chain into a dispatcher-consumable `Filter`. The bridge
 * is the boundary between the chain runtime and the dispatcher protocol;
 * its acceptance contract is documented in the class docblock.
 *
 * @internal
 */
final class MagicFilterAsFilterTest extends TestCase
{
  public function testBridgeIsAFilterSubclass(): void
  {
    $bridge = new MagicFilterAsFilter(MagicFilter::root());

    self::assertInstanceOf(Filter::class, $bridge);
  }

  public function testReturnsTrueForTruthyChainResult(): void
  {
    // Chain that evaluates to `true` → bridge returns `true`.
    $bridge = new MagicFilterAsFilter(MagicFilter::root()->id->equals(7));

    self::assertTrue($bridge(new Chat(id: 7, type: 'private')));
  }

  public function testReturnsFalseForFalsyChainResult(): void
  {
    $bridge = new MagicFilterAsFilter(MagicFilter::root()->id->equals(7));

    self::assertFalse($bridge(new Chat(id: 8, type: 'private')));
  }

  public function testReturnsFalseForNullChainResult(): void
  {
    // A chain that resolved to null — typically because an attribute
    // was missing and nothing rescued the rejection.
    $bridge = new MagicFilterAsFilter(MagicFilter::root()->missing);

    self::assertFalse($bridge(new Chat(id: 1, type: 'private')));
  }

  public function testPropagatesKwargShapedArrayVerbatim(): void
  {
    // The `as_('name')` terminal produces a string-keyed array that the
    // dispatcher consumes as kwargs. The bridge passes it through.
    $bridge = new MagicFilterAsFilter(MagicFilter::root()->id->as_('chatId'));

    self::assertSame(
      ['chatId' => 42],
      $bridge(new Chat(id: 42, type: 'private')),
    );
  }

  public function testCollapsesEmptyArrayToFalse(): void
  {
    // An empty array can arrive from a regexp-FINDALL miss combined
    // with as_(...) — semantically a rejection in the upstream contract.
    $emptyChain = MagicFilter::root()->regexp('zzz')->as_('m');
    $bridge = new MagicFilterAsFilter($emptyChain);

    self::assertFalse($bridge(new Chat(id: 1, type: 'private')));
  }

  public function testIntegerKeyedArrayCoerceToTrueWithoutKwargInjection(): void
  {
    // A purely numeric-keyed array (no kwargs) is treated as a truthy
    // value, not a kwarg map. The bridge returns `true` rather than
    // injecting numeric keys into the dispatcher's kwargs bag — which
    // would crash on positional-binding mode.
    //
    // Build a synthetic chain: `F->id->cast(...)` where the cast turns
    // the running int into a numeric-keyed list. Resolving against a
    // Chat surfaces the list; the bridge must collapse to `true`.
    $chain = MagicFilter::root()
      ->id
      ->cast(static fn(int $id): array => [$id, $id + 1]);
    $bridge = new MagicFilterAsFilter($chain);

    self::assertTrue($bridge(new Chat(id: 1, type: 'private')));
  }
}
