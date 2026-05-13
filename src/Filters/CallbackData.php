<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use BackedEnum;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Stringable;
use UnitEnum;

/**
 * Abstract base for typed callback-data payloads embedded inside
 * `InlineKeyboardButton::$callbackData`. Mirrors
 * `aiogram.filters.callback_data.CallbackData`
 * (`aiogram/filters/callback_data.py:34-149`).
 *
 * # Subclass shape
 *
 * Subclasses declare a class-level `#[CallbackPrefix('prefix', sep: ':')]`
 * attribute plus constructor-promoted readonly public properties:
 *
 *   #[CallbackPrefix('order')]
 *   final class OrderCallback extends CallbackData {
 *     public function __construct(
 *       public readonly int $id,
 *       public readonly string $action,
 *       public readonly bool $deleted,
 *     ) {}
 *   }
 *
 * The wire form is `prefix:val1:val2:...`. Both `pack()` and `unpack()`
 * iterate the **constructor parameter list** to determine which fields to
 * serialise and in what order. This guarantees:
 *
 * - Field order is always the constructor declaration order for both
 *   directions.
 * - Non-promoted `public readonly` properties assigned in the constructor
 *   body (derived/computed fields) are excluded from the wire form, just as
 *   Pydantic's `model_dump()` only includes model *fields* (declared in
 *   `__init__`), not arbitrary attributes set inside the body.
 *
 * The standard subclass shape remains constructor-promoted properties only,
 * which satisfies both constraints automatically.
 *
 * # Type-encoding table
 *
 * Mirrors `aiogram/filters/callback_data.py:67-82`:
 *
 *   - `null` → `''` (empty wire segment; nullable typed properties
 *     decode the empty segment back to `null`).
 *   - `bool` → `'1'` / `'0'` (matches upstream `str(int(value))`).
 *   - `int`, `float` → `(string)$value`.
 *   - `string` → as-is.
 *   - `\Stringable` (Decimal/BigDecimal/Fraction equivalents) →
 *     `(string)$value`.
 *   - `\UnitEnum` (backed enum) → `$value->value`.
 *   - Anything else → `\LogicException`. Programming error rather than
 *     user input, hence `LogicException` not `InvalidArgumentException`.
 *
 * UUID is mentioned in the upstream spec; we defer the dedicated branch
 * until `ramsey/uuid` is wired as a dependency. UUID objects that
 * implement `\Stringable` (the v4 default) flow through the Stringable
 * branch already.
 *
 * # Size cap
 *
 * Telegram limits `CallbackQuery::$data` to 64 UTF-8 bytes
 * (`MAX_CALLBACK_LENGTH = 64`). `pack()` measures byte length via
 * `strlen` — PHP strings are byte sequences, and the protocol expects
 * UTF-8, so byte count == UTF-8 byte count as long as the input is
 * properly UTF-8 (responsibility of the caller). Throws `\LogicException`
 * when the payload overflows: it's a programming error, not user input.
 */
abstract class CallbackData
{
  /** Telegram-imposed maximum wire length in UTF-8 bytes. */
  public const int MAX_CALLBACK_LENGTH = 64;

  /**
   * Encode `$this` into the wire form `prefix:val1:val2:...`.
   *
   * Iterates the constructor's parameter list (the same source `unpack()`
   * uses) rather than `getProperties(IS_PUBLIC)`. This guarantees:
   *
   * 1. Field order is always the constructor declaration order, matching
   *    `unpack()`'s iteration order exactly.
   * 2. Non-promoted `public readonly` properties that are computed inside
   *    the constructor body are excluded from the wire form — only
   *    constructor parameters are serialised, mirroring upstream's
   *    `model_dump()` which iterates Pydantic model *fields* (declared in
   *    the model's `__init__` signature), not arbitrary attributes set in
   *    the body.
   *
   * @throws LogicException If the subclass is missing the
   *                        `#[CallbackPrefix]` attribute, has no constructor,
   *                        contains an unencodable property value, or the
   *                        result exceeds 64 bytes.
   */
  public function pack(): string
  {
    $meta = self::reflectMeta(static::class);
    $parts = [$meta->prefix];

    $refl = new ReflectionClass(static::class);
    $ctor = $refl->getConstructor();

    if ($ctor === null) {
      throw new LogicException(sprintf(
        'CallbackData subclass %s has no constructor; cannot pack',
        static::class,
      ));
    }

    foreach ($ctor->getParameters() as $param) {
      $prop = $refl->getProperty($param->getName());
      $encoded = self::encodeValue($prop->getValue($this));

      if (str_contains($encoded, $meta->sep)) {
        // Upstream raises ValueError when a value contains the
        // separator (`aiogram/filters/callback_data.py:93-98`).
        // PHP equivalent: `InvalidArgumentException` — the value
        // is bad input, not a structural defect.
        throw new InvalidArgumentException(sprintf(
          'CallbackData value for "%s" contains separator "%s": %s',
          $param->getName(),
          $meta->sep,
          $encoded,
        ));
      }

      $parts[] = $encoded;
    }

    $payload = implode($meta->sep, $parts);
    $byteLen = strlen($payload);

    if ($byteLen > self::MAX_CALLBACK_LENGTH) {
      throw new LogicException(sprintf(
        'CallbackData payload exceeds 64 bytes (%d): %s',
        $byteLen,
        $payload,
      ));
    }

    return $payload;
  }

  /**
   * Decode a wire payload back into an instance of `static`. Iterates
   * the constructor parameters and maps each segment by position; the
   * declaration order MUST match the public property declaration order
   * (true by default for promoted-property subclasses).
   *
   * @return static
   *
   * @throws InvalidArgumentException When the prefix doesn't match.
   * @throws LogicException When the subclass is malformed (missing
   *                        attribute, missing constructor, undecodable target type).
   */
  public static function unpack(string $data): self
  {
    $meta = self::reflectMeta(static::class);
    $parts = explode($meta->sep, $data);
    $prefix = array_shift($parts);

    if ($prefix !== $meta->prefix) {
      throw new InvalidArgumentException(sprintf(
        'CallbackData prefix mismatch: expected "%s", got "%s"',
        $meta->prefix,
        $data,
      ));
    }

    $refl = new ReflectionClass(static::class);
    $ctor = $refl->getConstructor();

    if ($ctor === null) {
      throw new LogicException(sprintf(
        'CallbackData subclass %s has no constructor; cannot unpack',
        static::class,
      ));
    }

    $params = $ctor->getParameters();

    if (count($parts) !== count($params)) {
      // Upstream raises TypeError when arity doesn't match
      // (`aiogram/filters/callback_data.py:119-124`). The
      // `CallbackQueryFilter` catches `LogicException` so this becomes
      // a graceful `false` at dispatch time, but a user calling
      // `unpack()` directly still gets a clear error.
      throw new LogicException(sprintf(
        'CallbackData %s expected %d arguments, got %d',
        static::class,
        count($params),
        count($parts),
      ));
    }

    $args = [];

    foreach ($params as $i => $param) {
      $raw = $parts[$i];
      $args[$param->getName()] = self::decodeValue($raw, $param);
    }

    /** @var static */
    return $refl->newInstance(...$args);
  }

  /**
   * Build a `CallbackQueryFilter` bound to this subclass. Handler:
   *
   *   $router->callbackQuery()->register($fn, MyCallbackData::filter());
   *
   * Mirrors upstream's `cls.filter(rule=...)` classmethod
   * (`aiogram/filters/callback_data.py:141-149`). The `rule` (MagicFilter)
   * argument lands in Phase 4.5+; the current Task 4.8 surface keeps the
   * factory parameter-less.
   */
  public static function filter(): CallbackQueryFilter
  {
    return new CallbackQueryFilter(static::class);
  }

  // ---------------------------------------------------------------------
  // Internals
  // ---------------------------------------------------------------------

  /**
   * Read the `#[CallbackPrefix]` metadata for `$class`. Validates that
   * the attribute is present and that the separator is not contained
   * inside the prefix (mirroring upstream's `__init_subclass__` check at
   * `aiogram/filters/callback_data.py:59-64`).
   *
   * @param class-string $class
   */
  private static function reflectMeta(string $class): CallbackPrefix
  {
    $refl = new ReflectionClass($class);
    $attrs = $refl->getAttributes(CallbackPrefix::class);

    if ($attrs === []) {
      throw new LogicException(sprintf(
        'CallbackData subclass %s must declare #[CallbackPrefix(prefix: "...", sep: ":")]',
        $class,
      ));
    }

    $meta = $attrs[0]->newInstance();

    if ($meta->sep === '' || str_contains($meta->prefix, $meta->sep)) {
      // Empty separator → split would yield one segment per character;
      // separator inside prefix → unpack can't recover the boundary.
      // Both are configuration errors caught here at first use.
      throw new LogicException(sprintf(
        'CallbackData %s: separator "%s" must be non-empty and absent from prefix "%s"',
        $class,
        $meta->sep,
        $meta->prefix,
      ));
    }

    return $meta;
  }

  /**
   * Encode a single property value to its wire string form. See class
   * docblock for the full type-encoding table.
   */
  private static function encodeValue(mixed $value): string
  {
    return match (true) {
      $value === null => '',
      is_bool($value) => $value ? '1' : '0',
      is_int($value), is_float($value) => (string)$value,
      // UnitEnum branch must come BEFORE Stringable: a backed enum
      // could in principle implement `__toString`, but the wire form
      // is the enum's case value (`$value->value`), not its string
      // representation. Mirrors upstream's
      // `isinstance(value, Enum)` precedence at line 70-71.
      $value instanceof UnitEnum => self::encodeEnum($value),
      $value instanceof Stringable => (string)$value,
      is_string($value) => $value,
      default => throw new LogicException(
        'CallbackData cannot encode value of type ' . get_debug_type($value),
      ),
    };
  }

  /**
   * Encode a UnitEnum value. Backed enums (`enum X: string {}` /
   * `enum X: int {}`) expose `->value`; pure UnitEnums fall back to the
   * case name. Mirrors upstream's `str(value.value)` which works for
   * Python's `Enum` (both backed and unbacked, where the backing value
   * is the case name string by default).
   */
  private static function encodeEnum(UnitEnum $value): string
  {
    if (property_exists($value, 'value')) {
      // BackedEnum case: `->value` is `int|string`.
      /** @var int|string $backing */
      $backing = $value->value;

      return (string)$backing;
    }

    // Pure UnitEnum fallback — the case name is the only stable
    // identifier we can put on the wire.
    return $value->name;
  }

  /**
   * Decode a wire segment back to a typed value. The parameter
   * reflection gives us the target type; we dispatch per scalar/complex.
   * Nullable parameters with an empty wire segment decode according to
   * upstream `callback_data.py:131-137`:
   *
   *   if v == "" and nullable and field.default != "":
   *       return field.default if field.default is not PydanticUndefined else None
   *
   * Upstream contract decomposed:
   *
   * 1. Empty wire + nullable + non-empty-string default → return default.
   * 2. Empty wire + nullable + no default → return `null`.
   * 3. Empty wire + nullable + empty-string default (`?string $foo = ''`) →
   *    fall through to `$raw` (i.e. `''`).  `field.default != ""` fails, so
   *    upstream does NOT return default/null — the empty wire round-trips as
   *    the empty string.
   * 4. Empty wire + non-nullable → fall through to scalar coercion path
   *    (may raise `TypeError` for int/bool/float).
   */
  private static function decodeValue(string $raw, ReflectionParameter $param): mixed
  {
    $type = $param->getType();

    if ($raw === '') {
      $hasDefault = $param->isDefaultValueAvailable();
      $default = $hasDefault ? $param->getDefaultValue() : null;
      $isNullable = $type instanceof ReflectionNamedType && $type->allowsNull();

      // Case 1: nullable + non-empty-string default → return default.
      // Mirrors `field.default != ""` guard in upstream:
      //   parsed_value = field.default if field.default is not PydanticUndefined else None
      if ($isNullable && $hasDefault && $default !== '') {
        return $default;
      }

      // Case 2: nullable + no default → return null.
      // `field.default is PydanticUndefined` branch: upstream returns None.
      if ($isNullable && !$hasDefault) {
        return null;
      }

      // Case 3 (nullable + empty-string default) and Case 4 (non-nullable):
      // fall through to the coercion path below.  For `?string $foo = ''`
      // this returns `''`, restoring upstream parity.  For non-nullable
      // types the coercion may raise TypeError.
    }

    if (!$type instanceof ReflectionNamedType) {
      // Union/intersection types are out of scope for the encoder
      // (we can't know which arm of the union to coerce into), so we
      // pass the raw string through and let the constructor decide.
      return $raw;
    }

    return match ($type->getName()) {
      'int' => (int)$raw,
      'float' => (float)$raw,
      'bool' => self::decodeBool($raw),
      'string' => $raw,
      default => self::decodeComplex($raw, $type),
    };
  }

  /**
   * Decode a boolean wire segment. Accepts `'1'`/`'0'` (our preferred
   * pack form) plus `'true'`/`'false'` (case-insensitive) for forward
   * compatibility with upstream's `_encode_value` accepting either.
   */
  private static function decodeBool(string $raw): bool
  {
    return $raw === '1' || strcasecmp($raw, 'true') === 0;
  }

  /**
   * Decode into a complex (non-scalar) target type. Currently supports
   * backed enums via `::from($raw)`. Other complex types (Stringable
   * value objects, UUIDs, etc.) require subclass-side overrides because
   * we can't know how to reconstruct an arbitrary value object from a
   * plain wire string.
   */
  private static function decodeComplex(string $raw, ReflectionNamedType $type): mixed
  {
    $name = $type->getName();

    if (enum_exists($name)) {
      /** @var class-string<BackedEnum> $name */
      return $name::from($raw);
    }

    throw new LogicException(sprintf(
      'CallbackData cannot decode value "%s" into type %s; '
        . 'override unpack() in your subclass to handle this type',
      $raw,
      $name,
    ));
  }
}
