<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use BackedEnum;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
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
 * The wire form is `prefix:val1:val2:...`. `pack()` walks the public
 * properties via reflection (preserving constructor declaration order)
 * and encodes each via the type-encoding table below. `unpack()` reads
 * back into the constructor by parameter name.
 *
 * Constraint: **constructor parameter order MUST match the public
 * property declaration order.** With promoted properties this is the
 * natural shape — the two orders are necessarily identical — so the
 * constraint only bites if a subclass mixes promoted and explicit
 * properties. Subclasses that need that pattern should override
 * `pack()`/`unpack()` themselves.
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
   * @throws LogicException If the subclass is missing the
   *                        `#[CallbackPrefix]` attribute, contains an unencodable property
   *                        value, or the result exceeds 64 bytes.
   */
  public function pack(): string
  {
    $meta = self::reflectMeta(static::class);
    $parts = [$meta->prefix];

    $refl = new ReflectionClass(static::class);

    foreach ($refl->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
      if ($prop->isStatic()) {
        // Skip class-level constants/static helpers — only instance
        // properties carry payload data. Matches upstream's
        // `model_dump` which iterates fields, not class vars.
        continue;
      }

      $encoded = self::encodeValue($prop->getValue($this));

      if (str_contains($encoded, $meta->sep)) {
        // Upstream raises ValueError when a value contains the
        // separator (`aiogram/filters/callback_data.py:93-98`).
        // PHP equivalent: `InvalidArgumentException` — the value
        // is bad input, not a structural defect.
        throw new InvalidArgumentException(sprintf(
          'CallbackData value for "%s" contains separator "%s": %s',
          $prop->getName(),
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
   * Nullable parameters with an empty wire segment decode to `null`,
   * regardless of whether the type itself is a string.
   */
  private static function decodeValue(string $raw, ReflectionParameter $param): mixed
  {
    $type = $param->getType();

    if ($raw === '') {
      if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
        return null;
      }

      if ($param->isDefaultValueAvailable()) {
        // Mirror upstream's `parsed_value = field.default if ...`
        // branch for fields whose default is a non-empty value.
        return $param->getDefaultValue();
      }
      // Non-nullable, defaultless target — return empty string and let
      // the typed property's coercion (string only) or
      // `newInstance()` raise. For `int`/`bool`/`float` PHP's strict
      // typing rejects the empty string with a clear TypeError.
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
