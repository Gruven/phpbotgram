<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\CallbackData;
use Gruven\PhpBotGram\Filters\CallbackPrefix;
use Gruven\PhpBotGram\Filters\CallbackQueryFilter;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `CallbackQueryFilter` — the runtime side of the
 * `CallbackData::filter()` factory. Mirrors upstream
 * `aiogram.filters.callback_data.CallbackQueryFilter`
 * (`aiogram/filters/callback_data.py:152-194`).
 *
 * On an inbound `CallbackQuery` the filter:
 *   1. Guards the event type and the presence of `data`.
 *   2. Delegates to `$callbackDataClass::unpack($data)`.
 *   3. Returns `['callback_data' => $parsed]` so the parsed instance
 *      reaches the handler as the `$callback_data` kwarg.
 *
 * Errors from `unpack()` (prefix mismatch, arity mismatch, …) collapse to
 * `false` so the dispatcher continues to the next handler — the upstream
 * behavior on `except (TypeError, ValueError)`.
 *
 * Upstream `tests/test_filters/test_callback_data.py` cases deliberately not ported:
 *
 * - `TestCallbackQueryFilter::test_str` — `Filter` and DTOs have no `__str__` / `__repr__`
 *   equivalents in the PHP port (API divergence: no PHP `__str__`/`__repr__` on filters/DTOs).
 * - `TestCallbackDataFilter::test_call` rows where `rule` is non-None (rows 1, 2, 3, 7) —
 *   scope deferral: PHP `CallbackQueryFilter` does not yet implement the post-unpack
 *   rule-evaluation parameter (see /src/Filters/CallbackQueryFilter.php:35-38). The
 *   rule-None rows (4, 5, 6, 8) are covered by tests in this file.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class CallbackQueryFilterTest extends TestCase
{
  public function testIsAFilterSubclass(): void
  {
    // Smoke check — dispatcher cascading and `Filter::all/any` combinators
    // require every concrete filter to extend `Filter`.
    self::assertInstanceOf(Filter::class, new CallbackQueryFilter(CbqFixture::class));
  }

  public function testMatchReturnsKwargWithParsedCallbackData(): void
  {
    // Happy path: incoming `CallbackQuery::$data = 'cbq:5:edit:1'` decodes
    // into the bound subclass. Filter result is the kwargs bag mirroring
    // aiogram's `{"callback_data": callback_data}` return shape.
    $filter = CbqFixture::filter();
    $query = $this->callbackQuery(data: 'cbq:5:edit:1');

    $result = $filter($query);

    self::assertIsArray($result);
    self::assertArrayHasKey('callback_data', $result);
    self::assertInstanceOf(CbqFixture::class, $result['callback_data']);
    self::assertSame(5, $result['callback_data']->id);
    self::assertSame('edit', $result['callback_data']->action);
    self::assertTrue($result['callback_data']->deleted);
  }

  public function testNonMatchOnWrongPrefixReturnsFalse(): void
  {
    // Wire payload begins with a different prefix — `unpack()` raises
    // `InvalidArgumentException`, the filter catches and reports `false`.
    // Mirrors upstream `except (TypeError, ValueError): return False`.
    $filter = CbqFixture::filter();
    $query = $this->callbackQuery(data: 'other:5:edit:1');

    self::assertFalse($filter($query));
  }

  public function testNonCallbackQueryEventReturnsFalse(): void
  {
    // Dispatcher might wire the filter onto the wrong observer; the type
    // guard rejects rather than crashing. Matches upstream `if not
    // isinstance(query, CallbackQuery) ...: return False`.
    $filter = CbqFixture::filter();
    $message = new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
    );

    self::assertFalse($filter($message));
  }

  public function testNullDataReturnsFalse(): void
  {
    // Inline keyboard buttons can carry `url`/`game` instead of `data`;
    // the inbound event then has `data = null`. Mirrors upstream `or not
    // query.data` short-circuit.
    $filter = CbqFixture::filter();
    $query = $this->callbackQuery(data: null);

    self::assertFalse($filter($query));
  }

  public function testEmptyStringDataReturnsFalse(): void
  {
    // Defense in depth: a hand-crafted `data = ''` should not slip through
    // to `unpack`. Same upstream short-circuit (Python `not '' == True`).
    $filter = CbqFixture::filter();
    $query = $this->callbackQuery(data: '');

    self::assertFalse($filter($query));
  }

  public function testMalformedDataWithWrongArityReturnsFalse(): void
  {
    // Wire payload has the right prefix but too few segments — `unpack()`
    // raises a LogicException (reflection on a missing constructor
    // parameter). The filter swallows it and reports `false` so the
    // dispatcher can continue past the broken segment without crashing
    // the loop.
    $filter = CbqFixture::filter();
    $query = $this->callbackQuery(data: 'cbq:5');

    self::assertFalse($filter($query));
  }

  public function testFilterStoresCallbackDataClass(): void
  {
    // The `callbackDataClass` field is part of the public surface so
    // tests / debug code can introspect what the filter targets. This
    // also keeps the constructor's `class-string<CallbackData>` annotation
    // honest — PHPStan won't complain about a phantom property access.
    $filter = new CallbackQueryFilter(CbqFixture::class);

    self::assertSame(CbqFixture::class, $filter->callbackDataClass);
  }

  public function testRejectsCallbackQueryWhenDecodeThrowsTypeError(): void
  {
    // `CbDataIntKindCarrier` uses an int-backed enum (`CbDataIntKindForFilter`).
    // `CallbackData::decodeComplex()` calls `BackedEnum::from($raw)` where
    // `$raw` is a string. Under `declare(strict_types=1)` this raises
    // `\TypeError` for int-backed enums. The filter must catch that and
    // return `false` rather than crashing the dispatcher loop — mirrors
    // upstream `except (TypeError, ValueError): return False`.
    $filter = new CallbackQueryFilter(CbDataIntKindCarrier::class);
    $query = $this->callbackQuery(data: 'iek:1');

    self::assertFalse($filter($query));
  }

  /**
   * Build a minimally populated `CallbackQuery` for filter exercise. Reuses
   * a shared user/chat fixture so each test case stays focused on the
   * data-string-related variations.
   */
  private function callbackQuery(?string $data): CallbackQuery
  {
    return new CallbackQuery(
      id: 'cq-1',
      fromUser: new User(id: 1, isBot: false, firstName: 'Ada'),
      chatInstance: 'inst',
      data: $data,
    );
  }
}

/**
 * Local fixture mirroring `CbDataFixture` from `CallbackDataTest` but kept
 * separate to keep each test file's fixtures self-contained.
 *
 * @internal
 */
#[CallbackPrefix('cbq')]
final class CbqFixture extends CallbackData
{
  public function __construct(
    public readonly int $id,
    public readonly string $action,
    public readonly bool $deleted,
  ) {}
}

/**
 * Int-backed enum fixture for `testRejectsCallbackQueryWhenDecodeThrowsTypeError`.
 * Under `declare(strict_types=1)`, `BackedEnum::from(string)` on an int-backed
 * enum raises `\TypeError` — this is the deferred bug exercised defensively.
 *
 * @internal
 */
enum CbDataIntKindForFilter: int
{
  case First = 1;
  case Second = 2;
}

/**
 * CallbackData carrier that embeds the int-backed `CbDataIntKindForFilter`
 * enum so `decodeComplex()` will attempt `CbDataIntKindForFilter::from('1')`
 * (string arg) and throw `\TypeError` under strict_types.
 *
 * @internal
 */
#[CallbackPrefix('iek')]
final class CbDataIntKindCarrier extends CallbackData
{
  public function __construct(
    public readonly CbDataIntKindForFilter $kind,
  ) {}
}
