<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use DateTimeImmutable;
use Exception;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Unspecified;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use RuntimeException;
use TypeError;

/**
 * Walks a TelegramObject/TelegramMethod into a snake_case-keyed array
 * (dump) and back (load). Bot threading + InputFile detachment + JSON
 * encoding all live in BaseSession::prepareValue.
 */
final class Serializer
{
  /**
   * Dumps to snake_case keys. Skips Unspecified values; preserves nulls
   * (BaseSession::prepareValue strips them downstream per the null-filter rule).
   *
   * Accepts any BotContextController (TelegramObject or TelegramMethod) so that
   * AmphpSession can serialise method parameters without a separate code path.
   *
   * Per-class WireNames const overrides the default camelToSnake mapping:
   *   public const array WireNames = ['fromUser' => 'from'];
   * lets the property $fromUser serialize as wire key `from`.
   *
   * @return array<string, mixed>
   */
  public static function dump(BotContextController $object): array
  {
    $r = new ReflectionClass($object);
    $aliases = $r->hasConstant('WireNames') ? (array)$r->getConstant('WireNames') : [];
    $result = [];

    foreach ($r->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
      if ($prop->getName() === 'bot') {
        continue;
      }
      $value = $prop->getValue($object);

      if ($value === Unspecified::instance()) {
        continue;
      }
      $phpName = $prop->getName();
      $key = is_string($aliases[$phpName] ?? null) ? $aliases[$phpName] : self::camelToSnake($phpName);
      $result[$key] = self::dumpValue($value);
    }

    return $result;
  }

  private static function dumpValue(mixed $value): mixed
  {
    // Use BotContextController (not TelegramObject) — TelegramMethod also extends
    // BotContextController directly, and Serializer::dump accepts it. Without this
    // a nested TelegramMethod would slip through and hit json_encode (which then
    // invokes BotDefault::jsonSerialize → LogicException).
    if ($value instanceof BotContextController) {
      return self::dump($value);
    }

    if (is_array($value)) {
      $result = [];
      $isList = array_is_list($value);

      foreach ($value as $k => $item) {
        if ($item === Unspecified::instance()) {
          continue;
        }
        $dumped = self::dumpValue($item);

        if ($isList) {
          $result[] = $dumped;
        } else {
          $result[$k] = $dumped;
        }
      }

      return $result;
    }

    return $value;
  }

  /**
   * Loads a TelegramObject from a snake_case dict, recursively binding $bot.
   *
   * @template T of TelegramObject
   *
   * @param class-string<T> $class
   * @param array<string, mixed> $data
   *
   * @return T
   */
  public static function load(string $class, array $data, ?Bot $bot = null): TelegramObject
  {
    $r = new ReflectionClass($class);
    $ctor = $r->getConstructor()
      ?? throw new ClientDecodeException('Class has no constructor', new RuntimeException("{$class} has no constructor"), $data);
    $aliases = $r->hasConstant('WireNames') ? (array)$r->getConstant('WireNames') : [];
    $args = [];

    foreach ($ctor->getParameters() as $param) {
      if ($param->getName() === 'bot') {
        // Inject only when the class actually declares a $bot parameter;
        // user-defined BotContextController subclasses without it would otherwise
        // hard-fail at newInstance(...) with "unknown named parameter".
        $args['bot'] = $bot;

        continue;
      }
      $phpName = $param->getName();
      $wireName = is_string($aliases[$phpName] ?? null) ? $aliases[$phpName] : self::camelToSnake($phpName);

      if (!array_key_exists($wireName, $data)) {
        if ($param->isDefaultValueAvailable()) {
          continue;
        }

        if ($param->allowsNull()) {
          // Nullable param with no default — pass null explicitly so `newInstance`
          // doesn't ArgumentCountError on the missing slot.
          $args[$phpName] = null;

          continue;
        }

        throw new ClientDecodeException(
          "Missing required key '{$wireName}' for {$class}",
          new RuntimeException("Missing required key '{$wireName}'"),
          $data,
        );
      }
      $args[$phpName] = self::loadValue($param, $data[$wireName], $bot);
    }

    try {
      /** @var T */
      return $r->newInstance(...$args);
    } catch (TypeError $e) {
      // Wire payload type-mismatch (e.g. {"chat": null} against `readonly Chat $chat`)
      // is a remote-data error, not a programming bug — wrap so callers can catch
      // ClientDecodeException and recover the same way upstream catches pydantic
      // ValidationError around model_validate.
      throw new ClientDecodeException("Type mismatch constructing {$class}", $e, $data);
    }
  }

  private static function loadValue(ReflectionParameter $param, mixed $value, ?Bot $bot): mixed
  {
    $type = $param->getType();

    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
      $typeName = $type->getName();

      if (is_a($typeName, DateTimeImmutable::class, allow_string: true)) {
        // Unix-timestamp fields arrive as int on the wire but are typed as Custom\DateTime
        // or plain DateTimeImmutable (matching aiogram's `datetime.datetime` mapping).
        // Defensive: also accept ISO-8601 strings so codegen / future schema shapes
        // don't fall through to a raw TypeError at newInstance().
        if (is_int($value)) {
          return DateTime::fromTimestamp($value);
        }

        if (is_string($value)) {
          try {
            return new DateTime($value);
          } catch (Exception $e) {
            // Fall through to default return; let TypeError surface meaningfully.
          }
        }
      }

      if (is_subclass_of($typeName, TelegramObject::class) && is_array($value)) {
        /** @var class-string<TelegramObject> $typeName */
        return self::load($typeName, self::toStringKeyedArray($value), $bot);
      }
    }

    // Union types: discriminated unions emit a <Name>Union helper with resolve();
    // fallback to first TelegramObject member otherwise.
    if ($type instanceof ReflectionUnionType && is_array($value)) {
      foreach ($type->getTypes() as $member) {
        if (!$member instanceof ReflectionNamedType || $member->isBuiltin()) {
          continue;
        }
        $memberName = $member->getName();
        $unionClass = $memberName . 'Union';

        if (class_exists($unionClass) && method_exists($unionClass, 'resolve')) {
          /** @var callable(array<mixed>, ?Bot): mixed $resolver */
          $resolver = [$unionClass, 'resolve'];

          return $resolver($value, $bot);
        }

        if (is_subclass_of($memberName, TelegramObject::class)) {
          /** @var class-string<TelegramObject> $memberName */
          return self::load($memberName, self::toStringKeyedArray($value), $bot);
        }
      }
    }

    return $value;
  }

  /**
   * Narrows an array with unknown key type to string-keyed for PHPStan.
   * Wire data from Telegram always uses string keys; this assertion is safe.
   *
   * @param array<mixed, mixed> $arr
   *
   * @return array<string, mixed>
   */
  private static function toStringKeyedArray(array $arr): array
  {
    $result = [];

    foreach ($arr as $k => $v) {
      $result[(string)$k] = $v;
    }

    return $result;
  }

  /** @var array<string, string> */
  private static array $camelToSnakeCache = [];

  private static function camelToSnake(string $camel): string
  {
    return self::$camelToSnakeCache[$camel] ??= strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
  }
}
