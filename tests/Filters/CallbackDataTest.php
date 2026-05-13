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
 * Coverage for `CallbackData` — abstract base for typed callback-data
 * payloads. Mirrors `aiogram.filters.callback_data.CallbackData`
 * (`aiogram/filters/callback_data.py:34-149`).
 *
 * Subclasses declare a class-level `#[CallbackPrefix('prefix', sep: ':')]`
 * attribute plus constructor-promoted readonly public properties. The base
 * walks those properties via reflection to encode/decode the wire string.
 *
 * Test fixtures live at the bottom of this file so the test class can
 * exercise its own subclasses without polluting the namespace with one-shot
 * stubs.
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
