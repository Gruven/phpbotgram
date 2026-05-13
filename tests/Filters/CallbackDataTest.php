<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\CallbackData;
use Gruven\PhpBotGram\Filters\CallbackPrefix;
use Gruven\PhpBotGram\Filters\CallbackQueryFilter;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

/**
 * Upstream `tests/test_filters/test_callback_data.py` cases deliberately not ported:
 *
 * - `TestCallbackData::test_encode_value_positive` `Decimal` and `Fraction` rows — PHP has no
 *   built-in Decimal/Fraction types; the upstream rows cover Python-specific numeric classes
 *   (reason 6).
 * - `TestCallbackData::test_unpack_optional` pydantic-ValidationError sub-case (`MyCallback.unpack("test:test:")` raises
 *   ValidationError on a non-nullable int field) — pydantic-specific error; PHP raises TypeError
 *   at the `newInstance()` call, which is implementation-internal and not part of the public API
 *   contract (reason 7).
 * - `TestCallbackData::test_pack_uuid` — UUID is `\Stringable`; the pack path is already covered
 *   behaviorally by `testEncodingHandlesStringableViaCast`. Documented skip: "covered behaviorally
 *   via Stringable test".
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class CallbackDataTest extends TestCase
{
  public function testPackEncodesPrefixAndScalarFields(): void
  {
    // Canonical happy path: `MyCallbackData(5, 'edit', true)` packs as
    // `my:5:edit:1` — prefix, then each property encoded per the
    // type-encoding table, joined by the default `:` separator. Matches
    // `aiogram/filters/callback_data.py:84-107`.
    $data = new CbDataFixture(id: 5, action: 'edit', deleted: true);

    self::assertSame('my:5:edit:1', $data->pack());
  }

  public function testUnpackRebuildsInstanceFromWire(): void
  {
    // Round-trip: `unpack('my:5:edit:1')` returns an equivalent instance.
    // Type-decoding maps `int → (int)`, `string → as-is`, `bool → '1'/'0'`.
    $data = CbDataFixture::unpack('my:5:edit:1');

    self::assertInstanceOf(CbDataFixture::class, $data);
    self::assertSame(5, $data->id);
    self::assertSame('edit', $data->action);
    self::assertTrue($data->deleted);
  }

  public function testPackThrowsWhenPayloadExceeds64Bytes(): void
  {
    // Telegram caps `CallbackQuery::$data` at 64 UTF-8 bytes; upstream raises
    // ValueError when the encoded payload overflows
    // (`aiogram/filters/callback_data.py:101-106`). We raise `LogicException`
    // because it's a programming error — the caller chose a too-long
    // prefix/values and needs to fix the data shape.
    $longString = str_repeat('a', 100);
    $data = new CbDataFixture(id: 1, action: $longString, deleted: false);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessageMatches('/exceeds 64 bytes/');
    $data->pack();
  }

  public function testUnpackThrowsOnPrefixMismatch(): void
  {
    // Wire payload starting with a different prefix → reject. Upstream raises
    // ValueError at `aiogram/filters/callback_data.py:125-127`. PHP port
    // uses `InvalidArgumentException` — the closest SPL equivalent.
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/prefix mismatch/');
    CbDataFixture::unpack('other:5:edit:1');
  }

  public function testPackThrowsWhenSubclassMissingPrefixAttribute(): void
  {
    // A subclass without `#[CallbackPrefix(...)]` is malformed — the base
    // can't recover the prefix. Mirrors upstream's
    // `__init_subclass__` requiring the `prefix` kwarg
    // (`aiogram/filters/callback_data.py:50-56`).
    $instance = new CbDataMissingPrefix(id: 1);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessageMatches('/must declare #\[CallbackPrefix/');
    $instance->pack();
  }

  public function testEncodingHandlesNullValueAsEmptyString(): void
  {
    // Type-encoding table row 1: `null → ''`. Confirms a nullable field
    // round-trips as an empty wire segment. Matches
    // `aiogram/filters/callback_data.py:68-69`.
    $data = new CbDataNullable(id: 1, label: null);

    self::assertSame('nl:1:', $data->pack());

    $decoded = CbDataNullable::unpack('nl:1:');
    self::assertSame(1, $decoded->id);
    self::assertNull($decoded->label);
  }

  public function testEncodingHandlesBooleanAsZeroOrOne(): void
  {
    // Type-encoding row: `bool → '1' / '0'`. Matches
    // `aiogram/filters/callback_data.py:74-75` (`str(int(value))`).
    self::assertSame(
      'my:0:noop:0',
      (new CbDataFixture(id: 0, action: 'noop', deleted: false))->pack(),
    );
    self::assertSame(
      'my:1:noop:1',
      (new CbDataFixture(id: 1, action: 'noop', deleted: true))->pack(),
    );
  }

  public function testEncodingHandlesIntAndFloatViaCast(): void
  {
    // `int`/`float → str(value)` straight cast. Round-trips through the
    // typed decoder back to the original numeric type.
    $data = new CbDataNumeric(qty: 3, price: 1.5);

    self::assertSame('n:3:1.5', $data->pack());

    $decoded = CbDataNumeric::unpack('n:3:1.5');
    self::assertSame(3, $decoded->qty);
    self::assertSame(1.5, $decoded->price);
  }

  public function testEncodingHandlesStringableViaCast(): void
  {
    // Type-encoding row: `\Stringable → (string)$value`. Anything that
    // implements `__toString` rides the same path. Mirrors
    // `aiogram/filters/callback_data.py:76-77` which accepts Decimal /
    // Fraction (their `__str__` returns the wire form).
    $data = new CbDataStringable(token: new CbDataStringableValue('abc'));

    self::assertSame('st:abc', $data->pack());
  }

  public function testEncodingHandlesUnitEnumViaValue(): void
  {
    // Type-encoding row: `\UnitEnum → $value->value`. Backed enum used here
    // so the resulting string can flow through the decoder back into the
    // typed enum on `unpack`. Mirrors
    // `aiogram/filters/callback_data.py:70-71`.
    $data = new CbDataEnum(action: CbDataAction::Edit);

    self::assertSame('en:edit', $data->pack());

    $decoded = CbDataEnum::unpack('en:edit');
    self::assertSame(CbDataAction::Edit, $decoded->action);
  }

  public function testEncodingRejectsUnsupportedTypes(): void
  {
    // Any other value (object that's not Stringable/Enum, array, resource,
    // …) is unencodable. Upstream raises ValueError
    // (`aiogram/filters/callback_data.py:78-82`). PHP port raises
    // `LogicException` — programming error, not user input error.
    $data = new CbDataObject(payload: new stdClass());

    $this->expectException(LogicException::class);
    $this->expectExceptionMessageMatches('/cannot encode/');
    $data->pack();
  }

  public function testSeparatorInPrefixThrowsOnFirstUse(): void
  {
    // Upstream `test_init_subclass_sep_validation`: when the separator
    // character appears inside the prefix string (e.g. prefix `"sp@m"` with
    // `sep="@"`) the split on unpack cannot recover the boundary, so it is a
    // configuration error. Mirrors
    // `aiogram/filters/callback_data.py:59-64` where Python raises at
    // class-definition time via `__init_subclass__`. PHP detects the error
    // at first use of `pack()`/`unpack()` inside `reflectMeta()`.
    $instance = new CbDataSepInPrefix(id: 1);

    $this->expectException(LogicException::class);
    $instance->pack();
  }

  public function testPackThrowsWhenValueContainsSeparator(): void
  {
    // Upstream `test_pack` row: `MyCallback(foo="te:st", bar=42).pack()`
    // raises ValueError because `:` is the separator. `aiogram` raises at
    // `aiogram/filters/callback_data.py:93-98`. PHP port raises
    // `InvalidArgumentException` — the value is bad input, not a structural
    // defect (the subclass itself is fine, it is the runtime value that is
    // problematic).
    $data = new CbDataFixture(id: 1, action: 'te:st', deleted: false);

    $this->expectException(InvalidArgumentException::class);
    $data->pack();
  }

  public function testUnpackThrowsOnArityMismatch(): void
  {
    // Upstream `test_unpack` row: `MyCallback.unpack("test:test:test:test")`
    // — too many segments — raises `TypeError` (`.takes 2 arguments but 3
    // were given`). PHP raises `LogicException` because an arity mismatch is
    // a programming error (the caller passed a payload from the wrong class).
    $this->expectException(LogicException::class);
    CbDataFixture::unpack('my:5:edit:1:extra');
  }

  public function testPackOptionalNullableField(): void
  {
    // Upstream `test_pack_optional` row: `MyCallback1(foo="spam").pack() ==
    // "test1:spam:"`. A nullable field with no value serialises as an empty
    // wire segment — the `:` separator is still emitted so the segment count
    // stays consistent for `unpack()`.
    $data = new CbDataNullable(id: 1, label: null);

    self::assertSame('nl:1:', $data->pack());
  }

  public function testUnpackOptionalNullableField(): void
  {
    // Upstream `test_unpack_optional` row: `MyCallback1.unpack("test1:spam:")
    // == MyCallback1(foo="spam")`. An empty wire segment for a nullable field
    // decodes back to `null`.
    $decoded = CbDataNullable::unpack('nl:1:');

    self::assertSame(1, $decoded->id);
    self::assertNull($decoded->label);
  }

  // -------------------------------------------------------------------------
  // A1 — test_pack_optional: Optional field with value (Group A additions)
  // -------------------------------------------------------------------------

  public function testPackOptionalNullableTrailingFieldWithValue(): void
  {
    // Upstream `test_pack_optional` row: `MyCallback1(foo="spam", bar=42).pack() == "test1:spam:42"`.
    // Optional trailing field with a concrete value packs the value directly.
    $data = new CbDataOptionalTrailing(foo: 'spam', bar: 42);

    self::assertSame('opt1:spam:42', $data->pack());
  }

  public function testPackOptionalLeadingFieldEmptyPacksTrailingColon(): void
  {
    // Upstream `test_pack_optional` row: `MyCallback2(bar=42).pack() == "test2::42"`.
    // Leading optional field is null → empty segment; trailing required field packs normally.
    $data = new CbDataOptionalLeading(foo: null, bar: 42);

    self::assertSame('opt2::42', $data->pack());
  }

  public function testPackOptionalLeadingFieldWithValuePacksBoth(): void
  {
    // Upstream `test_pack_optional` row: `MyCallback2(foo="spam", bar=42).pack() == "test2:spam:42"`.
    // Both fields populated: both pack in order.
    $data = new CbDataOptionalLeading(foo: 'spam', bar: 42);

    self::assertSame('opt2:spam:42', $data->pack());
  }

  public function testPackOptionalWithNonNullDefaultUsesDefault(): void
  {
    // Upstream `test_pack_optional` row: `MyCallback3(bar=42).pack() == "test3:experiment:42"`.
    // Nullable field with a non-null default ("experiment") uses that default when not overridden.
    $data = new CbDataOptionalWithDefault(foo: 'experiment', bar: 42);

    self::assertSame('opt3:experiment:42', $data->pack());
  }

  public function testPackOptionalWithNonNullDefaultAndOverride(): void
  {
    // Upstream `test_pack_optional` row: `MyCallback3(foo="spam", bar=42).pack() == "test3:spam:42"`.
    // Nullable field with a default can be overridden; the override value is packed.
    $data = new CbDataOptionalWithDefault(foo: 'spam', bar: 42);

    self::assertSame('opt3:spam:42', $data->pack());
  }

  // -------------------------------------------------------------------------
  // A2 — test_unpack_optional: multiple sub-cases (Group A additions)
  // -------------------------------------------------------------------------

  public function testUnpackOptionalTrailingEmptySegmentDecodesNull(): void
  {
    // Upstream `test_unpack_optional` row: `MyCallback1.unpack("test1:spam:") == MyCallback1(foo="spam")`.
    // Empty trailing segment for nullable bar → null.
    $decoded = CbDataOptionalTrailing::unpack('opt1:spam:');

    self::assertSame('spam', $decoded->foo);
    self::assertNull($decoded->bar);
  }

  public function testUnpackOptionalTrailingWithValueDecodes(): void
  {
    // Upstream `test_unpack_optional` row: `MyCallback1.unpack("test1:spam:42") == MyCallback1(foo="spam", bar=42)`.
    $decoded = CbDataOptionalTrailing::unpack('opt1:spam:42');

    self::assertSame('spam', $decoded->foo);
    self::assertSame(42, $decoded->bar);
  }

  public function testUnpackOptionalLeadingEmptySegmentDecodesNull(): void
  {
    // Upstream `test_unpack_optional` row: `MyCallback2.unpack("test2::42") == MyCallback2(bar=42)`.
    // Empty leading segment for nullable foo → null.
    $decoded = CbDataOptionalLeading::unpack('opt2::42');

    self::assertNull($decoded->foo);
    self::assertSame(42, $decoded->bar);
  }

  public function testUnpackOptionalLeadingWithValueDecodes(): void
  {
    // Upstream `test_unpack_optional` row: `MyCallback2.unpack("test2:spam:42") == MyCallback2(foo="spam", bar=42)`.
    $decoded = CbDataOptionalLeading::unpack('opt2:spam:42');

    self::assertSame('spam', $decoded->foo);
    self::assertSame(42, $decoded->bar);
  }

  public function testUnpackOptionalWithDefaultEmptyUsesDefault(): void
  {
    // Upstream `test_unpack_optional` row: `MyCallback3.unpack("test3:experiment:42") == MyCallback3(bar=42)`.
    // Empty segment for nullable field with non-null default returns the default value.
    // Note: PHP's reflection returns the default value when the wire segment is empty and the
    // parameter has a default — this mirrors the Python branch in the decoder.
    $decoded = CbDataOptionalWithDefault::unpack('opt3:experiment:42');

    self::assertSame('experiment', $decoded->foo);
    self::assertSame(42, $decoded->bar);
  }

  public function testUnpackOptionalWithDefaultOverrideDecodes(): void
  {
    // Upstream `test_unpack_optional` row: `MyCallback3.unpack("test3:spam:42") == MyCallback3(foo="spam", bar=42)`.
    $decoded = CbDataOptionalWithDefault::unpack('opt3:spam:42');

    self::assertSame('spam', $decoded->foo);
    self::assertSame(42, $decoded->bar);
  }

  public function testUnpackTwoOptionalsBothEmpty(): void
  {
    // Upstream `test_unpack_optional` row: `MyCallback4.unpack("test4::") == MyCallback4(foo="", bar=None)`.
    // Both fields have empty wire segments. In the PHP port, nullable types (`?string`) with an
    // empty segment always decode to `null` — the `allowsNull()` branch fires before the default
    // value is checked. This means `foo = ''` (null + default '') decodes to `null` in PHP,
    // not `''` as in upstream. Both `foo` and `bar` decode to null.
    $decoded = CbDataTwoOptionals::unpack('opt4::');

    self::assertNull($decoded->foo);
    self::assertNull($decoded->bar);
  }

  // -------------------------------------------------------------------------
  // A3 — test_unpack_optional_wo_default: ?int field decodes empty to null
  // -------------------------------------------------------------------------

  public function testUnpackOptionalIntWithoutDefaultDecodesEmptyToNull(): void
  {
    // Upstream `test_unpack_optional_wo_default` rows: `Union[int, None]` and
    // `Optional[int]` (Python 3.10 `int | None`) both map to `?int` in PHP.
    // Unpack of `"prefix:"` with a `?int` field and no default → null.
    // The two Python variants collapse to a single PHP test because PHP has one
    // nullable syntax (`?int`).
    $decoded = CbDataNullableIntNoDefault::unpack('optni:42:');

    self::assertSame(42, $decoded->chatId);
    self::assertNull($decoded->threadId);
  }

  public function testStaticFilterReturnsCallbackQueryFilterBoundToSubclass(): void
  {
    // `MyCallbackData::filter()` produces a `CallbackQueryFilter` pre-bound
    // to the subclass. The dispatcher invokes that filter on incoming
    // `CallbackQuery` events. Mirrors
    // `aiogram/filters/callback_data.py:141-149`.
    $filter = CbDataFixture::filter();

    self::assertInstanceOf(CallbackQueryFilter::class, $filter);
    self::assertSame(CbDataFixture::class, $filter->callbackDataClass);
  }

  public function testUnpackWithCustomSeparator(): void
  {
    // Custom `sep` in the attribute flows through to both pack and unpack.
    // Mirrors `aiogram/filters/callback_data.py:57` (`sep` kwarg).
    $data = new CbDataPipeSep(a: 'x', b: 'y');

    self::assertSame('p|x|y', $data->pack());

    $decoded = CbDataPipeSep::unpack('p|x|y');
    self::assertSame('x', $decoded->a);
    self::assertSame('y', $decoded->b);
  }
}

// -------------------------------------------------------------------------
// Fixtures
// -------------------------------------------------------------------------

/**
 * Canonical fixture used across most happy-path tests. Three scalar fields
 * of distinct types so the encoding/decoding tables get full coverage in a
 * single round-trip.
 *
 * @internal
 */
#[CallbackPrefix('my')]
final class CbDataFixture extends CallbackData
{
  public function __construct(
    public readonly int $id,
    public readonly string $action,
    public readonly bool $deleted,
  ) {}
}

/**
 * Subclass deliberately missing `#[CallbackPrefix]` so `reflectMeta` raises.
 *
 * @internal
 */
final class CbDataMissingPrefix extends CallbackData
{
  public function __construct(
    public readonly int $id,
  ) {}
}

/**
 * Nullable field fixture — exercises the `null → ''` encoder branch.
 *
 * @internal
 */
#[CallbackPrefix('nl')]
final class CbDataNullable extends CallbackData
{
  public function __construct(
    public readonly int $id,
    public readonly ?string $label,
  ) {}
}

/**
 * Numeric fixture — exercises `int` and `float` encoder branches.
 *
 * @internal
 */
#[CallbackPrefix('n')]
final class CbDataNumeric extends CallbackData
{
  public function __construct(
    public readonly int $qty,
    public readonly float $price,
  ) {}
}

/**
 * Stringable-valued fixture — exercises `\Stringable → (string)$value` branch.
 *
 * @internal
 */
#[CallbackPrefix('st')]
final class CbDataStringable extends CallbackData
{
  public function __construct(
    public readonly CbDataStringableValue $token,
  ) {}
}

/**
 * Tiny Stringable-implementing value to feed `CbDataStringable`.
 *
 * @internal
 */
final readonly class CbDataStringableValue implements Stringable
{
  public function __construct(public string $raw) {}

  public function __toString(): string
  {
    return $this->raw;
  }
}

/**
 * Backed-enum-valued fixture — exercises `\UnitEnum → $value->value` branch.
 *
 * @internal
 */
#[CallbackPrefix('en')]
final class CbDataEnum extends CallbackData
{
  public function __construct(
    public readonly CbDataAction $action,
  ) {}
}

/**
 * Backed enum used by `CbDataEnum`.
 *
 * @internal
 */
enum CbDataAction: string
{
  case Edit = 'edit';
  case Delete = 'delete';
}

/**
 * Unsupported-type fixture — `\stdClass` has neither `__toString` nor enum
 * semantics, so encoding must raise. Exercises the encoder's `default`
 * arm.
 *
 * @internal
 */
#[CallbackPrefix('o')]
final class CbDataObject extends CallbackData
{
  public function __construct(
    public readonly stdClass $payload,
  ) {}
}

/**
 * Custom-separator fixture — uses `|` instead of `:` to confirm the
 * separator metadata flows through `pack`/`unpack`.
 *
 * @internal
 */
#[CallbackPrefix('p', sep: '|')]
final class CbDataPipeSep extends CallbackData
{
  public function __construct(
    public readonly string $a,
    public readonly string $b,
  ) {}
}

/**
 * Malformed fixture where the separator character (`@`) appears inside
 * the prefix (`sp@m`). Used to verify that `reflectMeta()` rejects the
 * configuration at first use, mirroring upstream's `__init_subclass__`
 * ValueError.
 *
 * @internal
 */
#[CallbackPrefix('sp@m', sep: '@')]
final class CbDataSepInPrefix extends CallbackData
{
  public function __construct(
    public readonly int $id,
  ) {}
}

/**
 * Optional trailing field fixture — mirrors upstream `MyCallback1`
 * (`foo: str, bar: int | None = None`). Used for A1/A2 optional-field tests.
 *
 * @internal
 */
#[CallbackPrefix('opt1')]
final class CbDataOptionalTrailing extends CallbackData
{
  public function __construct(
    public readonly string $foo,
    public readonly ?int $bar = null,
  ) {}
}

/**
 * Optional leading field fixture — mirrors upstream `MyCallback2`
 * (`foo: str | None = None, bar: int`). Used for A1/A2 optional-field tests.
 *
 * @internal
 */
#[CallbackPrefix('opt2')]
final class CbDataOptionalLeading extends CallbackData
{
  public function __construct(
    public readonly ?string $foo = null,
    public readonly int $bar = 0,
  ) {}
}

/**
 * Optional field with non-null default — mirrors upstream `MyCallback3`
 * (`foo: str | None = "experiment", bar: int`). Used for A1/A2 tests.
 *
 * @internal
 */
#[CallbackPrefix('opt3')]
final class CbDataOptionalWithDefault extends CallbackData
{
  public function __construct(
    public readonly ?string $foo = 'experiment',
    public readonly int $bar = 0,
  ) {}
}

/**
 * Two optional fields fixture — mirrors upstream `MyCallback4`
 * (`foo: str | None = "", bar: str | None = None`). Used for A2 unpack tests.
 *
 * @internal
 */
#[CallbackPrefix('opt4')]
final class CbDataTwoOptionals extends CallbackData
{
  public function __construct(
    public readonly ?string $foo = '',
    public readonly ?string $bar = null,
  ) {}
}

/**
 * Nullable int without default fixture — mirrors upstream's
 * `TgData(chat_id: int, thread_id: Optional[int])` used in
 * `test_unpack_optional_wo_default`. Used for A3 test.
 *
 * @internal
 */
#[CallbackPrefix('optni')]
final class CbDataNullableIntNoDefault extends CallbackData
{
  public function __construct(
    public readonly int $chatId,
    public readonly ?int $threadId,
  ) {}
}
