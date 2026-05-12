<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use DateTimeImmutable;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Unspecified;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

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
    if ($value instanceof TelegramObject) {
      return self::dump($value);
    }

    if (is_array($value)) {
      return array_map([self::class, 'dumpValue'], $value);
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
    $ctor = $r->getConstructor() ?? throw new LogicException("{$class} has no constructor");
    $aliases = $r->hasConstant('WireNames') ? (array)$r->getConstant('WireNames') : [];
    $args = [];

    foreach ($ctor->getParameters() as $param) {
      if ($param->getName() === 'bot') {
        $args['bot'] = $bot;

        continue;
      }
      $phpName = $param->getName();
      $wireName = is_string($aliases[$phpName] ?? null) ? $aliases[$phpName] : self::camelToSnake($phpName);

      if (!array_key_exists($wireName, $data)) {
        if ($param->isDefaultValueAvailable()) {
          continue;
        }

        throw new LogicException("Missing key '{$wireName}' for {$class}");
      }
      $args[$phpName] = self::loadValue($param, $data[$wireName], $bot);
    }

    /** @var T */
    return $r->newInstance(...$args);
  }

  private static function loadValue(ReflectionParameter $param, mixed $value, ?Bot $bot): mixed
  {
    $type = $param->getType();

    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
      $typeName = $type->getName();

      // Unix-timestamp fields arrive as int on the wire but are typed as Custom\DateTime
      // or plain DateTimeImmutable (matching aiogram's `datetime.datetime` mapping).
      if (is_int($value) && is_a($typeName, DateTimeImmutable::class, allow_string: true)) {
        return DateTime::fromTimestamp($value);
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

  private static function camelToSnake(string $camel): string
  {
    return strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
  }
}
