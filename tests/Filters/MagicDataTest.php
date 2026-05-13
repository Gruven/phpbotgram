<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\MagicData;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `MagicData` — `Filter` subclass that resolves a `MagicFilter`
 * chain against the dispatch data dict (`['event' => $event, ...$kwargs]`).
 *
 * Mirrors upstream `aiogram.filters.magic_data.MagicData` (which resolves the
 * chain against an `AttrDict` made of the kwargs bag, with `event` already
 * injected by the dispatcher). In our PHP port the bridge wraps the kwargs
 * in an `AttrDict` so chains like `F->event->text->equals('hi')`,
 * `F->state->equals(...)`, and `F->config->key->equals(...)` all reach the
 * value via attribute-style access.
 */
/**
 * Upstream `tests/test_filters/test_magic_data.py` cases deliberately not ported:
 *
 * - `TestMagicData::test_call` integer-keyed positional kwargs row — Python's `AttrDict`
 *   supports positional numeric keys; PHP `MagicData::__invoke` accepts only named (string)
 *   kwargs, so integer-keyed positional access is not part of the PHP contract.
 * - `TestMagicData::test_str` — `Filter` and DTOs have no `__str__` / `__repr__` equivalents
 *   in the PHP port (reason 5).
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class MagicDataTest extends TestCase
{
  public function testIsAFilterSubclassAndStoresMagicFilter(): void
  {
    // The wrap-as-filter is the dispatcher entry point; the chain is the
    // inner `MagicFilter`. We expose the chain via a readonly property so
    // call sites and debuggers can recover the original chain.
    $magic = MagicFilter::root()->event->text->equals('hi');

    $filter = new MagicData($magic);

    self::assertInstanceOf(Filter::class, $filter);
    self::assertSame($magic, $filter->magicData);
  }

  public function testResolvesAgainstEventInjectedAsKwarg(): void
  {
    // Upstream `MagicData.__call__` injects the event under the `event`
    // key in the AttrDict it resolves against. Chains can reach into the
    // event payload via `F->event->...` exactly the way Python does.
    $filter = new MagicData(MagicFilter::root()->event->text->equals('hi'));

    self::assertTrue($filter($this->message(text: 'hi')));
  }

  public function testResolvesAgainstScalarKwarg(): void
  {
    // FSM-style usage: `F->state->equals(SomeState::Active)`. The chain
    // walks `data['state']` after we wrap kwargs in `AttrDict`.
    $filter = new MagicData(MagicFilter::root()->state->equals('active'));

    self::assertTrue($filter($this->message(), ['state' => 'active']));
    self::assertFalse($filter($this->message(), ['state' => 'inactive']));
  }

  public function testResolvesAgainstNestedArrayKwarg(): void
  {
    // Nested kwargs reach via `AttrDict::__get` → the value (a plain
    // array) is then consumed via `GetItemOperation`-fallback inside the
    // chain. Use a stdClass-shaped nested dict so the `->key->equals(...)`
    // chain traverses object-style.
    $filter = new MagicData(MagicFilter::root()->config->key->equals('value'));

    self::assertTrue(
      $filter($this->message(), ['config' => (object)['key' => 'value']]),
    );
  }

  public function testReturnsFalseOnMismatch(): void
  {
    // `F->event->text->equals('hi')` against a message with `text='bye'`
    // → `ComparatorOperation` resolves to `false`; bridge collapses to
    // `false`.
    $filter = new MagicData(MagicFilter::root()->event->text->equals('hi'));

    self::assertFalse($filter($this->message(text: 'bye')));
  }

  public function testReturnsKwargArrayForAsTerminal(): void
  {
    // `as_('parsed')` wraps the chain's final value in a `{name => value}`
    // map. The bridge passes the map through verbatim so the dispatcher
    // can merge it into handler kwargs.
    $filter = new MagicData(MagicFilter::root()->event->text->as_('parsed'));

    self::assertSame(
      ['parsed' => 'hello'],
      $filter($this->message(text: 'hello')),
    );
  }

  public function testReturnsFalseOnMissingAttribute(): void
  {
    // A chain that walks an attribute the underlying value doesn't
    // expose (`F->missing`) ends in a `null` running value — the
    // resolver's "reject-collapses-to-null" branch. The bridge surfaces
    // that as `false` rather than letting a `null` leak through.
    $filter = new MagicData(MagicFilter::root()->missing->equals('anything'));

    self::assertFalse($filter($this->message()));
  }

  private function message(?string $text = null): Message
  {
    return new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
      text: $text,
    );
  }
}
